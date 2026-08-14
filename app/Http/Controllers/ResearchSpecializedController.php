<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ResearchProject;
use App\Models\SeminarPanelMember;
use App\Models\SeminarQuestion;
use App\Models\SeminarSession;
use App\Models\SiwesAttendanceRecord;
use App\Models\SiwesEvaluation;
use App\Models\SiwesLogEntry;
use App\Models\SiwesPlacement;
use App\Models\Submission;
use App\Services\Media\MediaSecurityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ResearchSpecializedController extends Controller
{
    public function show(Request $request, ResearchProject $research): View
    {
        $this->authorize('view', $research);
        $placement = SiwesPlacement::with(['logs.creator', 'attendance', 'evaluations.evaluator'])->where('research_project_id', $research->id)->first();
        $seminar = SeminarSession::with(['panelMembers.user', 'questions.asker', 'questions.answerer', 'submission.grade'])->where('research_project_id', $research->id)->first();
        $eligibleSubmissions = Submission::query()->with('course.department.faculty')
            ->whereIn('type', ['siwes', 'seminar'])
            ->where(function ($query) use ($request, $research) {
                $query->where('user_id', $research->owner_id);
                if ($request->user()->isAdmin()) {
                    $query->orWhereHas('course.department.faculty', fn ($scope) => $scope->where('university_id', $research->university_id));
                }
            })->latest()->get();
        $panelUsers = $research->university->users()->whereIn('role', ['lecturer', 'department_admin', 'university_admin'])->where('is_active', true)->orderBy('first_name')->get();

        return view('research.specialized', compact('research', 'placement', 'seminar', 'eligibleSubmissions', 'panelUsers'));
    }

    public function storePlacement(Request $request, ResearchProject $research): RedirectResponse
    {
        $this->authorize('update', $research);
        $data = $request->validate([
            'submission_id' => ['required', 'integer', 'exists:submissions,id'],
            'organization_name' => ['required', 'string', 'max:255'],
            'organization_address' => ['nullable', 'string', 'max:2000'],
            'industry_sector' => ['nullable', 'string', 'max:255'],
            'industry_supervisor_name' => ['nullable', 'string', 'max:255'],
            'industry_supervisor_email' => ['nullable', 'email', 'max:255'],
            'industry_supervisor_phone' => ['nullable', 'string', 'max:50'],
            'started_on' => ['nullable', 'date'],
            'ended_on' => ['nullable', 'date', 'after_or_equal:started_on'],
            'required_hours' => ['nullable', 'integer', 'min:0', 'max:10000'],
        ]);
        $submission = $this->submissionFor($research, (int) $data['submission_id'], 'siwes');

        SiwesPlacement::updateOrCreate(
            ['research_project_id' => $research->id, 'submission_id' => $submission->id],
            $data + ['student_id' => $submission->user_id, 'status' => 'active'],
        );
        $this->link($research, $submission, 'siwes');

        return back()->with('success', 'SIWES placement connected to the existing submission workflow.');
    }

    public function storeLog(Request $request, ResearchProject $research, SiwesPlacement $placement): RedirectResponse
    {
        $this->authorize('update', $research); $this->assertPlacement($research, $placement);
        $data = $request->validate([
            'entry_date' => ['required', 'date'], 'period_type' => ['required', 'in:daily,weekly,monthly'],
            'hours' => ['required', 'integer', 'min:0', 'max:168'], 'title' => ['required', 'string', 'max:255'],
            'activities' => ['required', 'string', 'max:30000'], 'skills_learned' => ['nullable', 'string', 'max:20000'],
            'challenges' => ['nullable', 'string', 'max:20000'], 'status' => ['required', 'in:draft,submitted'],
        ]);
        $placement->logs()->create($data + ['created_by' => $request->user()->id]);
        $placement->update(['completed_hours' => (int) $placement->logs()->where('status', '!=', 'rejected')->sum('hours')]);
        return back()->with('success', 'SIWES logbook entry saved.');
    }

    public function reviewLog(Request $request, ResearchProject $research, SiwesLogEntry $log): RedirectResponse
    {
        $this->authorize('review', $research); abort_unless($log->placement?->research_project_id === $research->id, 404);
        $data = $request->validate(['status' => ['required', 'in:approved,revision_requested,rejected'], 'lecturer_comment' => ['nullable', 'string', 'max:10000'], 'employer_comment' => ['nullable', 'string', 'max:10000']]);
        $log->update($data + ['reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);
        return back()->with('success', 'Logbook review recorded.');
    }

    public function attendance(Request $request, ResearchProject $research, SiwesPlacement $placement): RedirectResponse
    {
        $this->authorize('update', $research); $this->assertPlacement($research, $placement);
        $data = $request->validate([
            'attendance_date' => ['required', 'date'], 'check_in_at' => ['nullable', 'date_format:H:i'], 'check_out_at' => ['nullable', 'date_format:H:i'],
            'hours_worked' => ['required', 'numeric', 'min:0', 'max:24'], 'status' => ['required', 'in:present,absent,excused,remote'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'], 'longitude' => ['nullable', 'numeric', 'between:-180,180'], 'note' => ['nullable', 'string', 'max:3000'],
        ]);
        $placement->attendance()->updateOrCreate(['attendance_date' => $data['attendance_date']], $data + ['verified_by_type' => $request->user()->isAdmin() || $request->user()->isLecturer() ? 'institution' : 'self', 'verified_by' => $request->user()->id]);
        return back()->with('success', 'SIWES attendance updated.');
    }

    public function evaluate(Request $request, ResearchProject $research, SiwesPlacement $placement): RedirectResponse
    {
        $this->authorize('review', $research); $this->assertPlacement($research, $placement);
        $data = $request->validate([
            'evaluator_type' => ['required', 'in:industry_supervisor,lecturer,department'],
            'attendance_score' => ['nullable', 'numeric', 'between:0,100'], 'technical_score' => ['nullable', 'numeric', 'between:0,100'],
            'conduct_score' => ['nullable', 'numeric', 'between:0,100'], 'report_score' => ['nullable', 'numeric', 'between:0,100'], 'comment' => ['nullable', 'string', 'max:10000'],
        ]);
        $scores = collect(['attendance_score','technical_score','conduct_score','report_score'])->map(fn ($key) => isset($data[$key]) ? (float) $data[$key] : null)->filter(fn ($value) => $value !== null);
        $placement->evaluations()->create($data + ['evaluator_id' => $request->user()->id, 'overall_score' => $scores->isNotEmpty() ? round($scores->avg(), 2) : null, 'submitted_at' => now()]);
        return back()->with('success', 'SIWES evaluation recorded without bypassing final human approval.');
    }

    public function storeSeminar(Request $request, ResearchProject $research, MediaSecurityService $media): RedirectResponse
    {
        $this->authorize('review', $research);
        $data = $request->validate([
            'submission_id' => ['required', 'integer', 'exists:submissions,id'], 'title' => ['required', 'string', 'max:255'],
            'scheduled_at' => ['required', 'date'], 'duration_minutes' => ['required', 'integer', 'min:10', 'max:480'],
            'venue' => ['nullable', 'string', 'max:255'], 'online_url' => ['nullable', 'url', 'max:2000'],
            'panel_user_ids' => ['nullable', 'array'], 'panel_user_ids.*' => ['integer', 'exists:users,id'], 'slides' => ['nullable', 'file'],
        ]);
        $submission = $this->submissionFor($research, (int) $data['submission_id'], 'seminar');
        $seminar = DB::transaction(function () use ($data, $request, $research, $submission, $media) {
            $seminar = SeminarSession::updateOrCreate(['research_project_id' => $research->id, 'submission_id' => $submission->id], [
                'scheduled_by' => $request->user()->id, 'title' => $data['title'], 'scheduled_at' => $data['scheduled_at'],
                'duration_minutes' => $data['duration_minutes'], 'venue' => $data['venue'] ?? null, 'online_url' => $data['online_url'] ?? null, 'status' => 'scheduled',
            ]);
            foreach (array_unique($data['panel_user_ids'] ?? []) as $userId) {
                abort_unless($research->university->users()->whereKey($userId)->whereIn('role', ['lecturer','department_admin','university_admin'])->exists(), 422, 'Panel members must belong to the research institution.');
                $seminar->panelMembers()->firstOrCreate(['user_id' => $userId], ['role' => 'panelist']);
            }
            if ($request->hasFile('slides')) {
                $asset = $media->store($request->file('slides'), $request->user(), $seminar, 'private', ['purpose' => 'seminar_slides', 'research_project_uuid' => $research->uuid]);
                $seminar->update(['slide_media_asset_id' => $asset->id]);
            }
            return $seminar;
        });
        $this->link($research, $submission, 'seminar');
        return back()->with('success', 'Seminar schedule, panel, and slides were linked to the existing submission.');
    }

    public function askQuestion(Request $request, ResearchProject $research, SeminarSession $seminar): RedirectResponse
    {
        $this->authorize('view', $research); $this->assertSeminar($research, $seminar);
        $data = $request->validate(['question' => ['required', 'string', 'max:10000']]);
        $seminar->questions()->create($data + ['asked_by' => $request->user()->id, 'status' => 'open']);
        return back()->with('success', 'Seminar question recorded.');
    }

    public function answerQuestion(Request $request, ResearchProject $research, SeminarQuestion $question): RedirectResponse
    {
        $this->authorize('view', $research); abort_unless($question->session?->research_project_id === $research->id, 404);
        abort_unless($request->user()->isAdmin() || $research->owner_id === $request->user()->id || $research->supervisor_id === $request->user()->id, 403);
        $data = $request->validate(['response' => ['required', 'string', 'max:20000']]);
        $question->update($data + ['status' => 'answered', 'answered_by' => $request->user()->id, 'answered_at' => now()]);
        return back()->with('success', 'Seminar response recorded.');
    }

    public function scorePanel(Request $request, ResearchProject $research, SeminarSession $seminar, SeminarPanelMember $panel): RedirectResponse
    {
        $this->authorize('review', $research); $this->assertSeminar($research, $seminar); abort_unless($panel->seminar_session_id === $seminar->id, 404);
        abort_unless($request->user()->isAdmin() || $panel->user_id === $request->user()->id, 403);
        $data = $request->validate(['score' => ['required', 'numeric', 'between:0,100'], 'comment' => ['nullable', 'string', 'max:10000']]);
        $panel->update($data + ['scored_at' => now()]);
        $average = $seminar->panelMembers()->whereNotNull('score')->avg('score');
        $seminar->update(['final_score' => $average ? round((float) $average, 2) : null]);
        return back()->with('success', 'Panel score saved; final approval remains a human workflow action.');
    }

    public function completeSeminar(Request $request, ResearchProject $research, SeminarSession $seminar): RedirectResponse
    {
        $this->authorize('review', $research); $this->assertSeminar($research, $seminar);
        $data = $request->validate(['moderator_notes' => ['nullable', 'string', 'max:20000'], 'status' => ['required', 'in:completed,corrections_required,cancelled']]);
        DB::transaction(function () use ($seminar, $data, $request) {
            $seminar->update($data + ['completed_at' => $data['status'] === 'completed' ? now() : null]);
            if ($data['status'] === 'completed' && $seminar->final_score !== null) {
                $seminar->submission->grade()->updateOrCreate([], ['user_id' => $request->user()->id, 'score' => $seminar->final_score, 'max_score' => 100, 'feedback' => $data['moderator_notes'] ?? null, 'is_final' => true]);
                $seminar->submission->update(['status' => 'graded', 'graded_at' => now()]);
            } elseif ($data['status'] === 'corrections_required') {
                $seminar->submission->update(['status' => 'correction_requested']);
            }
        });
        return back()->with('success', 'Seminar result synchronized with the existing grading and correction workflow.');
    }

    private function submissionFor(ResearchProject $research, int $id, string $type): Submission
    {
        $submission = Submission::with('course.department.faculty')->findOrFail($id);
        abort_unless($submission->type === $type, 422, 'Submission type mismatch.');
        abort_unless($submission->user_id === $research->owner_id, 422, 'The submission must belong to the research owner.');
        abort_unless($submission->course?->department?->faculty?->university_id === $research->university_id, 403);
        return $submission;
    }

    private function link(ResearchProject $research, Submission $submission, string $type): void
    {
        $research->specializedLinks()->updateOrCreate(['workspace_type' => $type, 'source_type' => $submission->getMorphClass(), 'source_id' => $submission->id], ['settings' => ['submission_uuid' => $submission->uuid, 'reuse_existing_submission_versions' => true, 'reuse_existing_grading_and_defense' => true]]);
        $research->update(['specialization_type' => $type]);
    }

    private function assertPlacement(ResearchProject $research, SiwesPlacement $placement): void { abort_unless($placement->research_project_id === $research->id, 404); }
    private function assertSeminar(ResearchProject $research, SeminarSession $seminar): void { abort_unless($seminar->research_project_id === $research->id, 404); }
}
