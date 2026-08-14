<?php

namespace App\Services;

use App\Enums\NotificationType;
use App\Models\AuditLog;
use App\Models\ResearchProject;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowInstance;
use App\Models\WorkflowStage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WorkflowService
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function start(WorkflowDefinition $definition, Model $subject, User $actor, array $context = []): WorkflowInstance
    {
        return DB::transaction(function () use ($definition, $subject, $actor, $context) {
            $initial = $definition->stages()->where('is_initial', true)->first() ?? $definition->stages()->orderBy('position')->firstOrFail();
            $instance = WorkflowInstance::create(['workflow_definition_id' => $definition->id, 'subject_type' => $subject::class, 'subject_id' => $subject->getKey(), 'current_stage_id' => $initial->id, 'started_by' => $actor->id, 'status' => 'active', 'context' => $context]);
            $instance->transitions()->create(['to_stage_id' => $initial->id, 'actor_id' => $actor->id, 'action' => 'started', 'metadata' => ['subject' => $subject::class, 'subject_id' => $subject->getKey()], 'created_at' => now()]);
            return $instance->load('currentStage', 'definition.stages');
        });
    }

    public function transition(WorkflowInstance $instance, string $targetKey, User $actor, string $action = 'advance', ?string $note = null): WorkflowInstance
    {
        return DB::transaction(function () use ($instance, $targetKey, $actor, $action, $note) {
            $instance->loadMissing('currentStage', 'definition.stages');
            $current = $instance->currentStage;
            $target = $instance->definition->stages->firstWhere('key', $targetKey);
            if (! $current || ! $target) throw ValidationException::withMessages(['stage' => 'The requested workflow stage is unavailable.']);
            $this->authorizeTransition($current, $target, $actor, $action);
            $subject = $this->resolveSubject($instance);
            $this->verifyRequirements($target, $subject);

            $instance->update(['current_stage_id' => $target->id, 'status' => $target->is_final ? 'completed' : 'active', 'completed_at' => $target->is_final ? now() : null, 'context' => array_merge($instance->context ?? [], ['stage_entered_at' => now()->toISOString(), 'stage_deadline_at' => $target->deadline_days ? now()->addDays($target->deadline_days)->toISOString() : null])]);
            $instance->transitions()->create(['from_stage_id' => $current->id, 'to_stage_id' => $target->id, 'actor_id' => $actor->id, 'action' => $action, 'note' => $note, 'metadata' => ['requirements_verified' => true], 'created_at' => now()]);

            if ($subject instanceof ResearchProject) {
                $subject->milestones()->where('workflow_stage_id', $current->id)->update(['status' => 'completed', 'completed_at' => now(), 'completed_by' => $actor->id]);
                $subject->milestones()->where('workflow_stage_id', $target->id)->where('status', 'pending')->update(['status' => 'in_progress']);
                $subject->update(['last_activity_at' => now()]);
                foreach (collect([$subject->owner, $subject->supervisor, $subject->coSupervisor])->filter()->unique('id') as $recipient) {
                    if ($recipient->id !== $actor->id) $this->notifications->send($recipient, NotificationType::SYSTEM_ANNOUNCEMENT, 'Research workflow updated', $subject->title.' moved to '.$target->name.'.', ['research_project_uuid' => $subject->uuid, 'stage' => $target->key]);
                }
            }

            AuditLog::log('workflow_transitioned', $actor->id, WorkflowInstance::class, $instance->id, $current->key, $target->key, request()?->ip(), request()?->userAgent(), 'current_stage_id');
            return $instance->fresh(['currentStage', 'definition.stages', 'transitions.actor']);
        });
    }

    public function nextStage(WorkflowInstance $instance): ?WorkflowStage
    {
        $instance->loadMissing('currentStage', 'definition.stages');
        return $instance->definition->stages->where('position', '>', $instance->currentStage?->position ?? -1)->sortBy('position')->first();
    }

    private function resolveSubject(WorkflowInstance $instance): ?Model
    {
        $type = $instance->subject_type;
        return class_exists($type) && is_subclass_of($type, Model::class) ? $type::find($instance->subject_id) : null;
    }

    private function verifyRequirements(WorkflowStage $target, ?Model $subject): void
    {
        $requirements = array_merge($target->settings['requirements'] ?? [], $target->requirements ?? []);
        if ($requirements === [] || ! $subject) return;
        if ($subject instanceof ResearchProject) {
            $subject->loadMissing('sections', 'corrections', 'latestValidationReport');
            if (($requirements['required_sections_approved'] ?? false) && $subject->sections->where('is_required', true)->contains(fn ($section) => $section->status !== 'approved')) {
                throw ValidationException::withMessages(['stage' => 'All required research sections must be approved before this transition.']);
            }
            if (($requirements['no_open_corrections'] ?? false) && $subject->corrections->whereIn('status', ['open','in_progress'])->isNotEmpty()) {
                throw ValidationException::withMessages(['stage' => 'Resolve all open corrections before this transition.']);
            }
            if ($requirements['validation_required'] ?? false) {
                $report = $subject->latestValidationReport;
                if (! $report || $report->status !== 'completed') throw ValidationException::withMessages(['stage' => 'A completed validation report is required.']);
                if (isset($requirements['minimum_readiness']) && (float) $report->readiness_score < (float) $requirements['minimum_readiness']) throw ValidationException::withMessages(['stage' => 'The readiness score is below the configured threshold.']);
                if (isset($requirements['maximum_similarity']) && (float) $report->similarity_score > (float) $requirements['maximum_similarity']) throw ValidationException::withMessages(['stage' => 'The similarity score exceeds the configured threshold and requires review.']);
            }
        }
    }

    private function authorizeTransition(WorkflowStage $current, WorkflowStage $target, User $actor, string $action): void
    {
        if (! $actor->isAdmin()) {
            $roles = $current->actor_roles ?? [];
            if ($roles !== [] && ! in_array($actor->role, $roles, true)) throw ValidationException::withMessages(['stage' => 'Your role cannot perform this workflow transition.']);
        }
        $allowed = $current->settings['allowed_transitions'] ?? [];
        if ($allowed !== [] && ! in_array($target->key, $allowed, true)) throw ValidationException::withMessages(['stage' => 'This workflow transition is not allowed.']);
        if ($allowed === [] && $action !== 'request_corrections' && $target->position !== $current->position + 1) throw ValidationException::withMessages(['stage' => 'Workflow stages must normally advance in order.']);
    }
}
