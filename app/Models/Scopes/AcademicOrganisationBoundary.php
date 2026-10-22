<?php

namespace App\Models\Scopes;

use App\Models\Course;
use App\Models\ResearchProject;
use App\Models\User;
use App\Services\AcademicOrganisationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/** Defence for list queries as well as route bindings; personal work stays personal. */
class AcademicOrganisationBoundary implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        // Do not resolve an auth guard while it is itself loading a user.
        $guard = app('auth')->guard();
        if (! method_exists($guard, 'hasUser') || ! $guard->hasUser()) {
            return;
        }
        $actor = $guard->user();
        if (! $actor || ! $actor->isAdmin() || $actor->isSuperAdmin()) {
            return;
        }
        $departments = app(AcademicOrganisationService::class)->departmentIds($actor)->select('departments.id');
        if ($model instanceof Course) {
            $builder->where(function ($q) use ($actor, $departments): void {
                $q->where(fn ($i) => $i->where('access_mode', 'institutional')->whereIn('department_id', $departments))
                    ->orWhere(fn ($p) => $p->where('access_mode', 'independent')->where(fn ($m) => $m->where('owner_user_id', $actor->id)->orWhereHas('lecturerAssignments', fn ($a) => $a->where('user_id', $actor->id))->orWhereHas('enrollments', fn ($a) => $a->where('user_id', $actor->id)->where('status', 'enrolled'))));
            });
        } elseif ($model instanceof ResearchProject) {
            $builder->where(function ($q) use ($actor, $departments): void {
                $q->where(fn ($i) => $i->where('workspace_mode', 'institutional')->whereIn('department_id', $departments))
                    ->orWhere(fn ($p) => $p->where('owner_id', $actor->id)->orWhere('supervisor_id', $actor->id)->orWhere('co_supervisor_id', $actor->id)->orWhereHas('memberRecords', fn ($a) => $a->where('user_id', $actor->id)));
            });
        } elseif ($model instanceof User) {
            $builder->where(function ($q) use ($actor, $departments): void {
                $q->where('users.id', $actor->id)->orWhereHas('institutionAffiliations', fn ($a) => $a->approved()->whereIn('department_id', $departments));
                if ($actor->isUniversityAdmin()) {
                    $q->orWhereHas('institutionAffiliations', fn ($a) => $a->approved()->where('university_id', $actor->university_id)->whereNull('department_id'));
                }
            });
        }
    }
}
