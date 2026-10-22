<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\DepartmentAdoptionRequest;
use App\Models\DepartmentOnboardingDraft;
use App\Models\Faculty;
use App\Models\InstitutionOnboardingRequest;
use App\Models\MediaAsset;
use App\Models\University;
use App\Services\InstitutionAdoptionService;
use App\Services\AcademicOrganisationService;
use App\Services\Media\MediaSecurityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class DepartmentOnboardingController extends Controller
{
    public function show(Request $request, AcademicOrganisationService $organisations)
    {
        $draft = DepartmentOnboardingDraft::firstOrCreate(['user_id' => $request->user()->id], ['data' => []]);
        $applications = DepartmentAdoptionRequest::query()->where('requester_user_id', $request->user()->id)->with(['university', 'department', 'reviewer'])->latest()->get();
        $directoryRequests = InstitutionOnboardingRequest::query()->where('requester_user_id', $request->user()->id)->where('metadata->directory_only', true)->latest()->get();
        $selected = ! empty($draft->data['university_id']) ? $organisations->directoryInstitution((int) $draft->data['university_id']) : null;

        return view('institution.department-onboarding', compact('draft', 'applications', 'directoryRequests', 'selected'));
    }

    public function saveDraft(Request $request)
    {
        $data = $request->validate([
            'step' => ['required', 'integer', 'between:1,4'], 'university_id' => ['nullable', 'integer', 'exists:universities,id'],
            'faculty_id' => ['nullable', 'integer', 'exists:faculties,id'], 'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'faculty_name' => ['nullable', 'string', 'max:255'], 'department_name' => ['nullable', 'string', 'max:255'], 'department_code' => ['nullable', 'string', 'max:30'],
            'representative_title' => ['nullable', 'string', 'max:150'], 'official_email' => ['nullable', 'email', 'max:255'], 'official_phone' => ['nullable', 'string', 'max:50'],
            'classification' => ['nullable', Rule::in(config('academic_adoption.classifications'))], 'plan_key' => ['nullable', Rule::in(['department_core', 'department_sponsored'])], 'evidence' => ['nullable', 'string', 'max:3000'],
        ]);
        $step = $data['step'];
        unset($data['step']);
        DepartmentOnboardingDraft::updateOrCreate(['user_id' => $request->user()->id], ['step' => $step, 'data' => $data]);

        return response()->json(['success' => true, 'saved_at' => now()->toIso8601String()]);
    }

    public function submit(Request $request, InstitutionAdoptionService $adoption, MediaSecurityService $media)
    {
        $data = $request->validate([
            'university_id' => ['required', 'integer', 'exists:universities,id'], 'faculty_id' => ['nullable', 'integer', 'exists:faculties,id'],
            'faculty_name' => ['required_without:faculty_id', 'nullable', 'string', 'max:255'], 'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'department_name' => ['required_without:department_id', 'nullable', 'string', 'max:255'], 'department_code' => ['nullable', 'string', 'max:30'],
            'classification' => ['required', Rule::in(config('academic_adoption.classifications'))],
            'representative_title' => ['required', 'string', 'max:150'], 'official_email' => ['required', 'email', 'max:255'], 'official_phone' => ['required', 'string', 'max:50'],
            'plan_key' => ['required', Rule::in(['department_core', 'department_sponsored'])], 'evidence' => ['required', 'string', 'max:3000'],
            'verification_document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'], 'authority_confirmed' => ['accepted'],
        ]);
        if ($request->hasFile('verification_document')) {
            $asset = $media->store($request->file('verification_document'), $request->user(), null, 'private', ['purpose' => 'department_verification'], 'local');
            $data['evidence_media_id'] = $asset->id;
        }
        $application = $adoption->createDepartmentAdoption($request->user(), University::findOrFail($data['university_id']), ! empty($data['faculty_id']) ? Faculty::findOrFail($data['faculty_id']) : null, ! empty($data['department_id']) ? Department::with('faculty')->findOrFail($data['department_id']) : null, $data);
        if (isset($asset)) {
            $asset->forceFill(['attachable_type' => $application->getMorphClass(), 'attachable_id' => $application->id])->save();
        }
        DepartmentOnboardingDraft::where('user_id', $request->user()->id)->delete();

        return redirect()->route('department.onboarding')->with('success', 'Application submitted. Platform Admin will verify your authority and department. No subscription or payment has been created.');
    }

    public function requestInstitution(Request $request, InstitutionAdoptionService $adoption)
    {
        $data = $request->validate(['institution_name' => ['required', 'string', 'max:255'], 'institution_type' => ['required', Rule::in(['university', 'polytechnic', 'college'])], 'state' => ['nullable', 'string', 'max:80'], 'website' => ['nullable', 'url', 'max:500']]);
        if ($match = $adoption->findPotentialMatch($data)) {
            return back()->withErrors(['institution_name' => 'A possible matching institution exists: '.$match->name.'. Search the directory before submitting another record.']);
        }
        abort_if(InstitutionOnboardingRequest::where('requester_user_id', $request->user()->id)->where('metadata->directory_only', true)->whereIn('status', ['pending', 'in_review'])->exists(), 422, 'Your directory addition request is already under review.');
        InstitutionOnboardingRequest::create($data + ['requester_user_id' => $request->user()->id, 'representative_title' => 'Department adoption applicant', 'request_type' => 'create_new', 'status' => 'pending', 'metadata' => ['directory_only' => true]]);

        return back()->with('success', 'Institution addition requested. This adds an academic directory identity only; it does not adopt AcadSyntra on behalf of the institution.');
    }

    public function evidence(Request $request, DepartmentAdoptionRequest $departmentRequest)
    {
        abort_unless($request->user()->isSuperAdmin() || (int) $departmentRequest->requester_user_id === (int) $request->user()->id, 403);
        $asset = MediaAsset::findOrFail(data_get($departmentRequest->metadata, 'evidence_media_id'));
        abort_unless($asset->visibility === 'private' && $asset->disk === 'local' && (int) $asset->owner_id === (int) $departmentRequest->requester_user_id && in_array($asset->scan_status, ['clean', 'skipped'], true), 403);

        return Storage::disk($asset->disk)->download($asset->path, $asset->original_name, ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store']);
    }
}
