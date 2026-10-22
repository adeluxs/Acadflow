<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\Errors\UserFacingError;
use App\Models\Course;
use App\Models\Department;
use App\Models\Enrollment;
use App\Models\Semester;
use App\Models\User;
use App\Services\InstitutionAdoptionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StudentImportController extends Controller
{
    public function showImportForm()
    {
        $user = Auth::user();
        $lookups = app(\App\Services\AdminLookupService::class);
        $selectedDepartment = $lookups->selected($user, 'departments', old('department_id', $user->isDepartmentAdmin() ? $user->department_id : null));
        $selectedSemester = $lookups->selected($user, 'semesters', old('semester_id'), ['department_id' => $selectedDepartment['id'] ?? 0]);

        return view('students.import', compact('selectedDepartment', 'selectedSemester'));
    }

    public function import(Request $request, InstitutionAdoptionService $adoption)
    {
        $actor = $request->user();
        abort_unless($actor && $actor->isAdmin(), 403);

        $request->validate([
            'csv_file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
            'department_id' => [
                'required',
                Rule::exists('departments', 'id')->where(function ($query) use ($actor) {
                    if (! $actor->isSuperAdmin()) {
                        $query->whereIn('faculty_id', function ($subquery) use ($actor) {
                            $subquery->select('id')->from('faculties')->where('university_id', $actor->university_id);
                        });
                    }
                    if ($actor->isDepartmentAdmin()) $query->where('id', $actor->department_id);
                }),
            ],
            'semester_id' => [
                'nullable',
                Rule::exists('semesters', 'id')->where(function ($query) use ($actor) {
                    if (! $actor->isSuperAdmin()) {
                        $query->whereIn('academic_session_id', function ($subquery) use ($actor) {
                            $subquery->select('id')->from('academic_sessions')->where('university_id', $actor->university_id);
                        });
                    }
                }),
            ],
        ]);

        $handle = fopen($request->file('csv_file')->getRealPath(), 'r');
        if ($handle === false) return back()->with('error', 'Unable to open CSV file.');

        $header = fgetcsv($handle);
        if (! is_array($header)) {
            fclose($handle);
            return back()->with('error', 'The CSV file is empty or invalid.');
        }

        $header = array_map(fn ($value) => strtolower(trim((string) $value)), $header);
        foreach (['first_name', 'last_name', 'email', 'student_id'] as $requiredHeader) {
            if (! in_array($requiredHeader, $header, true)) {
                fclose($handle);
                return back()->with('error', "Missing required column: {$requiredHeader}");
            }
        }

        $department = Department::query()->with('faculty')->findOrFail($request->integer('department_id'));
        abort_unless(app(\App\Services\AcademicOrganisationService::class)->canManageDepartment($actor,$department),403);
        $targetUniversityId = (int) $department->faculty->university_id;
        $semester = $request->filled('semester_id')
            ? Semester::query()->with('academicSession')->findOrFail($request->integer('semester_id'))
            : null;
        if ($semester && ((int) $semester->academicSession?->university_id !== $targetUniversityId || ($semester->academicSession?->department_id && (int)$semester->academicSession->department_id !== (int)$department->id))) {
            fclose($handle);
            return back()->withInput()->with('error', 'The selected semester and department belong to different institutions.');
        }
        $imported = 0;
        $skipped = 0;
        $errors = [];
        $rowNumber = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;
            if (count($row) !== count($header)) {
                $skipped++;
                $errors[] = "Row {$rowNumber}: column count does not match the header.";
                continue;
            }

            $data = array_combine($header, $row);
            $email = strtolower(trim((string) ($data['email'] ?? '')));
            if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $skipped++;
                $errors[] = "Row {$rowNumber}: invalid email address.";
                continue;
            }

            try {
                $existing = User::withoutGlobalScopes()->where('email', $email)->first();
                $user = $existing ?: User::withoutGlobalScopes()->firstOrCreate(
                    ['email' => $email],
                    [
                        'first_name' => trim((string) ($data['first_name'] ?? '')),
                        'last_name' => trim((string) ($data['last_name'] ?? '')),
                        'student_id' => trim((string) ($data['student_id'] ?? '')) ?: null,
                        'phone' => trim((string) ($data['phone'] ?? '')) ?: null,
                        'password' => $existing?->password ?? Hash::make((string) (($data['password'] ?? '') ?: Str::password(16))),
                        'role' => 'student',
                        'university_id' => $targetUniversityId,
                        'department_id' => $request->integer('department_id'),
                        'is_active' => true,
                        'uuid' => $existing?->uuid ?? (string) Str::uuid(),
                    ]
                );

                $adoption->verifyAffiliation($user, $department->faculty->university, $department, 'student', $actor, 'roster_import', 'Verified through institution student import.');

                if ($semester && ! empty($data['course_id'])) {
                    $course = Course::query()
                        ->whereKey((int) $data['course_id'])
                        ->where('department_id', $request->integer('department_id'))
                        ->whereHas('department.faculty', fn (Builder $query) => $query->where('university_id', $targetUniversityId))
                        ->firstOrFail();

                    Enrollment::query()->updateOrCreate(
                        ['user_id' => $user->id, 'course_id' => $course->id, 'semester_id' => $semester->id],
                        ['status' => 'enrolled', 'enrolled_at' => now()]
                    );
                }

                $imported++;
            } catch (\Throwable $exception) {
                $skipped++;
                $errors[] = "Row {$rowNumber}: ".UserFacingError::safeMessage($exception->getMessage(), 'This row could not be imported.');
            }
        }

        fclose($handle);

        return back()->with('success', "Import completed. Imported: {$imported}, Skipped: {$skipped}.")
            ->with('import_errors', array_slice($errors, 0, 100));
    }
}
