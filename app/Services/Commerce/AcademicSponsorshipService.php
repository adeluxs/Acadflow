<?php

declare(strict_types=1);

namespace App\Services\Commerce;

use App\Models\CommercialAccount;
use App\Models\User;
use App\Services\AcademicOrganisationService;
use Illuminate\Support\Collection;

/** One resolver for fixed features, pay-per-use and AI; never moves balances. */
class AcademicSponsorshipService
{
    private function scopes(User $user): array
    {
        $organisations = app(AcademicOrganisationService::class);
        $membership = $organisations->membership($user);
        if (! $membership?->isVerified() || ! $organisations->available($membership->university, $membership->department)) {
            return [];
        }
        $department = $membership->department;
        $institutionEligible = $organisations->hasInstitutionAccess($user, (int) $membership->university_id);
        $departmentFirst = ! $department || data_get($department->adoption_settings, 'billing_owner', 'department') !== 'institution';
        $scopes = [];
        if ($department) {
            $scopes[] = ['department_id' => $department->id];
        }
        if ($institutionEligible) {
            $scopes[] = ['university_id' => $membership->university_id, 'department_id' => null];
        }
        if (! $departmentFirst) {
            $scopes = array_reverse($scopes);
        }

        return $scopes;
    }

    /** Read-only presentation snapshot; never reused for financial reservations. */
    public function accounts(User $user): Collection
    {
        $scopes = $this->scopes($user);
        if ($scopes === []) {
            return collect();
        }
        $accounts = CommercialAccount::query()->where(function ($q) use ($scopes): void {
            foreach ($scopes as $scope) {
                $q->orWhere(fn ($candidate) => $candidate->where($scope));
            }
        })->where('status', 'active')->where(fn ($q) => $q->whereNull('access_ends_at')->orWhere('access_ends_at', '>', now()))->get();

        return collect($scopes)->map(fn ($scope) => $accounts->first(function ($account) use ($scope): bool {
            foreach ($scope as $key => $value) {
                if ($value === null ? $account->$key !== null : (int) $account->$key !== (int) $value) {
                    return false;
                }
            }

            return $account->isAvailable();
        }))->filter()->values();
    }

    public function account(User $user, ?string $feature = null, bool $lock = false, bool $ai = false): ?CommercialAccount
    {
        if (! $lock) {
            return $this->accounts($user)->first(fn ($account) => (! $ai || (bool) data_get($account->metadata, 'sponsor_ai_usage', false))
                && (! $feature || $account->sponsorsFeature($feature)));
        }
        // Reservations retain fresh, ordered SELECT FOR UPDATE reads inside the
        // caller's transaction; UI snapshots never supply balances or authority.
        $scopes = $this->scopes($user);
        foreach ($scopes as $scope) {
            $query = CommercialAccount::query()->where($scope)->where('status', 'active')->where(fn ($q) => $q->whereNull('access_ends_at')->orWhere('access_ends_at', '>', now()));
            if ($lock) {
                $query->lockForUpdate();
            }
            $account = $query->first();
            if (! $account || ! $account->isAvailable()) {
                continue;
            }
            if ($ai && ! (bool) data_get($account->metadata, 'sponsor_ai_usage', false)) {
                continue;
            }
            if ($feature && ! $account->sponsorsFeature($feature)) {
                continue;
            }

            return $account;
        }

        return null;
    }
}
