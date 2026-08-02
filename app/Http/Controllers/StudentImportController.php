<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Department;
use App\Models\Enrollment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class StudentImportController extends Controller
{
    public function showImportForm()
    {
        $departments = Department::all();

        return view('students.import', compact('departments'));
    }

    public function import(Request $request)
    {
        $request->validate([
            'csv_file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
            'department_id' => ['required', 'exists:departments,id'],
            'semester_id' => ['nullable', 'exists:semesters,id'],
        ]);

        $file = $request->file('csv_file');
        $path = $file->getRealPath();
        $handle = fopen($path, 'r');

        if ($handle === false) {
            return back()->with('error', 'Unable to open CSV file.');
        }

        $header = fgetcsv($handle);
        $requiredHeaders = ['first_name', 'last_name', 'email', 'student_id'];

        foreach ($requiredHeaders as $requiredHeader) {
            if (! in_array($requiredHeader, array_map('strtolower', $header))) {
                fclose($handle);
                return back()->with('error', "Missing required column: {$requiredHeader}");
            }
        }

        $imported = 0;
        $skipped = 0;
        $errors = [];

        while (($row = fgetcsv($handle)) !== false) {
            $data = array_combine(array_map('strtolower', $header), $row);

            if (! $data || empty($data['email'])) {
                $skipped++;
                continue;
            }

            try {
                $user = User::updateOrCreate(
                    ['email' => $data['email']],
                    [
                        'first_name' => $data['first_name'] ?? '',
                        'last_name' => $data['last_name'] ?? '',
                        'student_id' => $data['student_id'] ?? null,
                        'phone' => $data['phone'] ?? null,
                        'password' => Hash::make($data['password'] ?? 'password123'),
                        'role' => 'student',
                        'university_id' => Auth::user()->university_id,
                        'department_id' => $request->department_id,
                        'is_active' => true,
                        'uuid' => \Illuminate\Support\Str::uuid(),
                    ]
                );

                if ($request->filled('semester_id')) {
                    Enrollment::firstOrCreate([
                        'user_id' => $user->id,
                        'course_id' => $data['course_id'] ?? null,
                        'semester_id' => $request->semester_id,
                    ]);
                }

                $imported++;
            } catch (\Throwable $e) {
                $skipped++;
                $errors[] = "Row ".(($imported + $skipped) + 1).": ".$e->getMessage();
            }
        }

        fclose($handle);

        return back()->with('success', "Import completed. Imported: {$imported}, Skipped: {$skipped}.")
            ->with('import_errors', $errors);
    }
}
