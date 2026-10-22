<?php

namespace App\Models\Scopes;

use App\Models\AcademicChallenge;
use App\Models\AcademicEvent;
use App\Models\Group;
use App\Models\KnowledgeCommunity;
use App\Models\KnowledgePublication;
use App\Services\AcademicOrganisationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class AcademicContentBoundary implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $guard = app('auth')->guard();
        if (! method_exists($guard, 'hasUser') || ! $guard->hasUser()) {
            return;
        }
        $actor = $guard->user();
        if (! $actor || ! $actor->academicContextApplied() || $actor->isSuperAdmin()) {
            return;
        }
        $service = app(AcademicOrganisationService::class);
        $institutionAccess = $service->hasInstitutionAccess($actor);
        $builder->where(function ($visible) use ($actor, $model, $institutionAccess): void {
            $visible->where($model->qualifyColumn('visibility'), '!=', 'institution');
            if ($actor->department_id) {
                $visible->orWhere(fn ($department) => $department->where($model->qualifyColumn('university_id'), $actor->university_id)->where($model->qualifyColumn('department_id'), $actor->department_id));
            }
            if ($institutionAccess) {
                $visible->orWhere(function ($institution) use ($actor, $model) {
                    $institution->where($model->qualifyColumn('university_id'), $actor->university_id)->where(function ($department) use ($model) {
                        $department->whereNull($model->qualifyColumn('department_id'))->orWhereHas('department', fn ($d) => $d->where('adoption_status', 'institution_managed')->orWhere(fn ($a) => $a->where('adoption_status', 'institution_affiliated')->where('adoption_settings->institution_data_access', true)));
                    });
                });
            }
            $owner = match (true) {
                $model instanceof KnowledgePublication => 'creator_id',$model instanceof Group => 'leader_id',$model instanceof KnowledgeCommunity => 'owner_id',default => 'organizer_id'
            };
            $visible->orWhere($model->qualifyColumn($owner), $actor->id);
            if ($model instanceof Group || $model instanceof KnowledgeCommunity) {
                $visible->orWhereHas('members', fn ($m) => $m->where('user_id', $actor->id)->where('status', 'active'));
            }
            if ($model instanceof AcademicEvent) {
                $visible->orWhereHas('registrations', fn ($m) => $m->where('user_id', $actor->id));
            }
            if ($model instanceof AcademicChallenge) {
                $visible->orWhereHas('entries', fn ($m) => $m->where('user_id', $actor->id));
            }
        });
    }
}
