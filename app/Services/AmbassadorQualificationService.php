<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AmbassadorApplication;
use App\Models\AmbassadorReferral;
use App\Models\AmbassadorReward;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AmbassadorQualificationService
{
    public function digest(string $purpose, string $value): string
    {
        return hash_hmac('sha256', $purpose.':'.$value, (string) config('app.key'));
    }

    public function emailHash(string $email): string
    {
        [$local, $domain] = explode('@', mb_strtolower(trim($email)), 2) + ['', ''];
        // Only Gmail guarantees that dots and + tags identify the same mailbox.
        if (in_array($domain, ['gmail.com', 'googlemail.com'], true)) {
            $domain = 'gmail.com';
            $local = str_replace('.', '', explode('+', $local, 2)[0]);
        }
        return $this->digest('mailbox', $local.'@'.$domain);
    }

    public function browserHash(Request $request): ?string
    {
        // Only the web middleware supplies this attribute after cookie decryption.
        $token = $request->attributes->get('ambassador_browser');
        return is_string($token) ? $this->digest('browser', $token) : null;
    }

    public function signals(User $user, Request $request): array
    {
        return [
            'browser_hash' => $this->browserHash($request),
            'email_hash' => $this->emailHash($user->email),
            // Hourly buckets reduce long-term network tracking; no raw IP is stored here.
            'ip_hash' => $request->ip() ? $this->digest('signup-network', $request->ip().'|'.now()->utc()->format('Y-m-d-H')) : null,
        ];
    }

    /** Called only after a real study artifact is persisted, never from activity middleware. */
    public function recordStudyAction(User $user, string $kind, string $content): void
    {
        if (! $user->has_ambassador_referral || ! $user->is_active || ! $user->onboarding_completed_at) return;
        if (! in_array($kind, ['study_note', 'reflection', 'practice_quiz'], true)) return;
        $content = mb_strtolower(trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($content), ENT_QUOTES | ENT_HTML5))));
        if (mb_strlen($content) < 80 || count(preg_split('/\s+/u', $content)) < 12) return;
        $referral = AmbassadorReferral::where('user_id', $user->id)
            ->where('registered_at', '<=', now())->where('retention_ends_at', '>=', now())
            ->where('qualification_status', 'pending')->first();
        if (! $referral) return;
        // At most 31 daily rows per referral; unique content prevents replaying the same work.
        DB::table('ambassador_referral_days')->insertOrIgnore([
            'ambassador_referral_id' => $referral->id, 'activity_date' => now()->utc()->toDateString(),
            'occurred_at' => now(), 'kind' => $kind, 'content_hash' => $this->digest('study-content', $content),
        ]);
    }

    /** Indexed correlated counts and batched relations, with no per-row queries in the review page. */
    public function evidence(Builder $query): Builder
    {
        $query->select('ambassador_referrals.*')->with(['user', 'application.user']);
        foreach (['browser_hash' => 'browser_matches', 'email_hash' => 'email_matches', 'ip_hash' => 'network_matches'] as $column => $alias) {
            $query->selectSub(DB::table('ambassador_referrals as other')->selectRaw('COUNT(*)')
                ->whereColumn('other.'.$column, 'ambassador_referrals.'.$column), $alias);
        }
        $query->selectSub(DB::table('ambassador_applications as a')->selectRaw('COUNT(*)')
            ->whereColumn('a.browser_hash', 'ambassador_referrals.browser_hash'), 'ambassador_browser_matches');
        $days = DB::table('ambassador_referral_days as d')->whereColumn('d.ambassador_referral_id', 'ambassador_referrals.id');
        $query->selectSub((clone $days)->selectRaw('COUNT(*)'), 'study_days');
        $query->selectSub((clone $days)->selectRaw('MAX(occurred_at)'), 'last_study_at');
        return $query;
    }

    /** Evidence must be loaded with evidence(); this method performs no queries. */
    public function assessment(AmbassadorReferral $referral): array
    {
        $user = $referral->user;
        $reasons = [];
        if (! $user || ! $user->is_active) $reasons[] = 'Account must be active.';
        if (! $user?->hasVerifiedEmail()) $reasons[] = 'Email ownership has not been verified.';
        if (! $user?->onboarding_completed_at) $reasons[] = 'Onboarding is incomplete.';
        if ($referral->registered_at->copy()->addDays(7)->isFuture()) $reasons[] = 'Account must be at least seven days old.';
        if ((int) $referral->study_days < 3) $reasons[] = 'Study actions are needed on three separate days.';
        if (! $referral->last_study_at || $referral->last_study_at < $referral->retention_starts_at->format('Y-m-d H:i:s')) {
            $reasons[] = 'A study action is needed between days 7 and 30.';
        }
        $flags = [];
        if (! $referral->browser_hash) $flags['missing_browser'] = 'No browser signal (API signup or older referral).';
        if (! $referral->email_hash || ! $referral->ip_hash) $flags['missing_signup_signals'] = 'Signup evidence is incomplete; independently verify this student.';
        if ((int) $referral->browser_matches > 1) $flags['shared_browser'] = 'Browser shared by '.$referral->browser_matches.' referred accounts.';
        if ((int) $referral->email_matches > 1) $flags['shared_mailbox'] = 'Mailbox or Gmail alias shared by '.$referral->email_matches.' referred accounts.';
        if ((int) $referral->network_matches >= 5) $flags['signup_burst'] = $referral->network_matches.' referrals from the same network in one clock hour.';
        if ((int) $referral->ambassador_browser_matches > 0) $flags['ambassador_browser'] = 'Browser also used to apply as an ambassador.';
        $owner = $referral->application?->user;
        if ($user && $owner && $this->emailHash($user->email) === $this->emailHash($owner->email)) {
            $flags['ambassador_mailbox'] = 'Referred email matches the ambassador mailbox or Gmail alias.';
        }
        if ($user && $referral->email_hash && $referral->email_hash !== $this->emailHash($user->email)) $flags['email_changed'] = 'Email differs from the signup mailbox.';
        $signature = $this->digest('review', json_encode([$flags, $user?->email, $referral->browser_hash, $referral->email_hash, $referral->ip_hash]));
        return ['eligible' => $reasons === [], 'reasons' => $reasons, 'flags' => $flags, 'signature' => $signature,
            'review_current' => $referral->review_signature && hash_equals($referral->review_signature, $signature)];
    }

    public function review(AmbassadorReferral $referral, User $actor, string $status, string $note, bool $acknowledgeRisks, string $evidenceSignature): void
    {
        abort_unless($actor->isSuperAdmin(), 403);
        DB::transaction(function () use ($referral, $actor, $status, $note, $acknowledgeRisks, $evidenceSignature) {
            $application = AmbassadorApplication::whereKey($referral->ambassador_application_id)->lockForUpdate()->firstOrFail();
            $locked = AmbassadorReferral::whereKey($referral->id)->lockForUpdate()->firstOrFail();
            abort_if(in_array($actor->id, [$application->user_id, $locked->user_id], true), 403);
            abort_unless(in_array($status, ['qualified', 'disqualified', 'pending'], true), 422);
            $evidence = $this->evidence(AmbassadorReferral::whereKey($locked->id))->firstOrFail();
            $check = $this->assessment($evidence);
            if (! hash_equals($check['signature'], $evidenceSignature)) {
                throw ValidationException::withMessages(['referral' => 'Evidence has changed. Refresh the referral review before making a decision.']);
            }
            if ($status === 'qualified') {
                if ($application->status !== 'approved' || ! $check['eligible']) {
                    throw ValidationException::withMessages(['referral' => array_merge(['Referral is not ready for qualification.'], $check['reasons'])]);
                }
                if ($check['flags'] && ! $acknowledgeRisks) {
                    throw ValidationException::withMessages(['acknowledge_risks' => 'Investigate the risk flags and confirm your findings before qualifying this referral.']);
                }
            }
            $previous = $locked->qualification_status;
            $locked->update(['qualification_status' => $status, 'reviewed_by' => $actor->id, 'reviewed_at' => now(),
                'review_note' => $note, 'review_signature' => $status === 'qualified' ? $check['signature'] : null]);
            AuditLog::log('ambassador.referral.'.$status, $actor->id, AmbassadorReferral::class, $locked->id, $previous,
                ['status' => $status, 'note' => $note, 'risk_flags' => $check['flags'], 'acknowledged' => $acknowledgeRisks]);
        }, 3);
    }

    /** Caller holds the application lock, serializing rewards and referral reviews. */
    public function assertRewardEligible(int $applicationId, ?int $referralId, ?int $rewardId = null): void
    {
        if (! $referralId) throw ValidationException::withMessages(['referral_id' => 'Performance bonuses require a reviewed, qualified referral.']);
        $referral = $this->evidence(AmbassadorReferral::whereKey($referralId)->where('ambassador_application_id', $applicationId))->first();
        $check = $referral ? $this->assessment($referral) : null;
        if (! $referral || $referral->qualification_status !== 'qualified' || ! $check['eligible'] || ! $check['review_current']) {
            throw ValidationException::withMessages(['referral_id' => 'This referral needs qualification or a fresh review before a reward can be issued or fulfilled.']);
        }
        if (AmbassadorReward::where('referral_id', $referralId)->whereIn('status', ['pending', 'fulfilled'])
            ->when($rewardId, fn ($q) => $q->where('id', '!=', $rewardId))->exists()) {
            throw ValidationException::withMessages(['referral_id' => 'This referral already backs a pending or fulfilled reward.']);
        }
    }
}
