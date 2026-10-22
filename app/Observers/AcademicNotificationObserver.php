<?php

namespace App\Observers;

use App\Jobs\RecordAcademicNotification;
use Illuminate\Database\Eloquent\Model;

class AcademicNotificationObserver
{
    public function saved(Model $model): void
    {
        // These reviewed transitions already publish their own domain events.
        if ($model instanceof \App\Models\InstitutionOnboardingRequest && ! $model->wasRecentlyCreated) return;
        if ($model instanceof \App\Models\DepartmentAdoptionRequest && $model->status === 'rejected') return;
        $changed = $model->wasRecentlyCreated || $model->wasChanged(['status', 'is_final', 'score', 'access_ends_at']);
        if (! $changed) return;
        RecordAcademicNotification::dispatch($model::class, (int) $model->getKey(), hash('sha256', json_encode([$model->getChanges(), $model->getRawOriginal('updated_at')])))->afterCommit();
    }
}
