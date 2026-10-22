<?php

namespace App\Http\Middleware;

use App\Services\AcademicOrganisationService;
use Closure;
use Illuminate\Http\Request;

class ResolveAcademicContext
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (! $user || $user->getRawOriginal('role') === 'super_admin' || $request->is('onboarding', 'onboarding/*')) {
            return $next($request);
        }

        $explicit = $request->hasHeader('X-Academic-Affiliation') || ($request->hasSession() && $request->session()->has('academic_affiliation_id'));
        $selection = $request->header('X-Academic-Affiliation', $request->hasSession() ? $request->session()->get('academic_affiliation_id') : null);
        abort_if($request->hasHeader('X-Academic-Affiliation') && ! ctype_digit((string) $selection), 422, 'Choose a valid academic affiliation.');
        $membership = null;
        if ($explicit && (int) $selection > 0) {
            $membership = $user->institutionAffiliations()->approved()->with(['university', 'department.faculty'])->find((int) $selection);
            if (! $membership || ! app(AcademicOrganisationService::class)->contextAvailable($membership)) {
                if ($request->hasHeader('X-Academic-Affiliation')) {
                    abort(403, 'This academic membership is no longer available.');
                }
                $request->session()->put('academic_affiliation_id', 0);
                $explicit = true;
            }
        } elseif (! $explicit) {
            $membership = $user->institutionAffiliations()->approved()->with(['university', 'department.faculty'])
                ->where('university_id', $user->getRawOriginal('university_id'))
                ->where('affiliation_type', $user->getRawOriginal('role'))->first();
        }
        if ($membership && ! app(AcademicOrganisationService::class)->contextAvailable($membership)) {
            $membership = null;
        }
        // Legacy administrators were assigned by trusted administrative code.
        // Keep their existing authority until a membership record manages it;
        // a revoked record must never reactivate this compatibility path.
        $legacyAdmin = ! $explicit && in_array($user->getRawOriginal('role'), ['university_admin', 'department_admin'], true)
            && ! $user->institutionAffiliations()->where('affiliation_type', $user->getRawOriginal('role'))->exists();
        if (! $legacyAdmin) {
            $user->useAcademicAffiliation($membership);
        }
        try {
            return $next($request);
        } finally {
            $user->clearAcademicContext();
        }
    }
}
