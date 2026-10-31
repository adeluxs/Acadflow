<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AmbassadorApplication;
use App\Models\AmbassadorReferral;
use App\Models\AmbassadorReport;
use App\Models\AmbassadorReward;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Commerce\WalletService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AmbassadorService
{
    /** Remembers a valid referral; invalid or inactive codes are ignored silently. */
    public function captureReferral(Request $request, string $code): bool
    {
        if ($request->user()) return false;
        $application = AmbassadorApplication::where('referral_code', $code)->where('status', 'approved')
            ->whereHas('user', fn ($q) => $q->where('is_active', true))->first();
        if (! $application) return false;
        $saved = $request->session()->get('ambassador_referral');
        // The first valid referral wins for 30 days in this browser session.
        if (is_array($saved) && ($saved['expires_at'] ?? 0) > now()->timestamp) return false;
        $request->session()->put('ambassador_referral', [
            'code' => $application->referral_code, 'expires_at' => now()->addDays(30)->timestamp,
        ]);
        return true;
    }

    public function attributeRegistration(User $user, Request $request): void
    {
        // This entrypoint is called only during registration, never login.
        if (! $user->wasRecentlyCreated) return;
        $saved = $request->hasSession() ? $request->session()->get('ambassador_referral') : null;
        $code = is_array($saved) && ($saved['expires_at'] ?? 0) > now()->timestamp
            ? ($saved['code'] ?? null) : $request->input('referral_code');
        if (! is_string($code) || ! preg_match('/^[a-zA-Z0-9]{16}$/', $code)) return;
        if (FeatureAccessService::effectiveStatus('campus_ambassadors', null) !== FeatureAccessService::STATUS_ENABLED) return;

        DB::transaction(function () use ($user, $code, $request): void {
            // Serialize attribution with approval/suspension; recheck at signup.
            $application = AmbassadorApplication::where('referral_code', strtolower($code))->lockForUpdate()->first();
            if (! $application || $application->status !== 'approved' || $application->user_id === $user->id) return;
            if (! User::withoutGlobalScopes()->whereKey($application->user_id)->where('is_active', true)->exists()) return;
            AmbassadorReferral::firstOrCreate(['user_id' => $user->id], [
                'ambassador_application_id' => $application->id,
                'registered_at' => now(),
                'retention_starts_at' => now()->addDays(7),
                'retention_ends_at' => now()->addDays(30),
            ] + app(AmbassadorQualificationService::class)->signals($user, $request));
            $user->forceFill(['has_ambassador_referral' => true])->save();
        });
        if ($request->hasSession()) $request->session()->forget('ambassador_referral');
    }

    /** Called by the existing five-minute activity recorder, never by page rendering. */
    public function recordActivity(User $user): void
    {
        // The marker comes from the already-authenticated user. Ordinary accounts
        // incur no query. Persist milestones before daily analytics are pruned.
        if (! $user->has_ambassador_referral || ! $user->is_active || ! $user->onboarding_completed_at) return;
        $at = now();
        DB::update('UPDATE ambassador_referrals
            SET activated_at = COALESCE(activated_at, ?),
                retained_at = CASE WHEN retention_starts_at <= ? AND retention_ends_at >= ?
                    THEN COALESCE(retained_at, ?) ELSE retained_at END
            WHERE user_id = ? AND registered_at <= ?
                AND (activated_at IS NULL OR (retained_at IS NULL AND retention_starts_at <= ? AND retention_ends_at >= ?))',
            [$at, $at, $at, $at, $user->id, $at, $at, $at]);
    }

    /** One aggregate query; no student identities or activity histories are returned. */
    public function metrics(?int $applicationId = null): array
    {
        $rows = DB::table('ambassador_referrals as r')->join('users as u', 'u.id', '=', 'r.user_id')
            ->when($applicationId !== null, fn ($q) => $q->where('r.ambassador_application_id', $applicationId))
            ->select('u.is_active', 'u.email_verified_at', 'r.activated_at', 'r.retained_at', 'r.qualification_status')
            ->selectSub(DB::table('commerce_orders as o')->selectRaw('1')->whereColumn('o.buyer_id', 'r.user_id')
                ->where('o.payment_status', 'paid')->where('o.total_amount_minor', '>', 0)
                ->whereColumn('o.paid_at', '>=', 'r.registered_at')->where('o.paid_at', '<=', now())->limit(1), 'paid_order');
        $result = DB::query()->fromSub($rows, 'referral_metrics')->selectRaw(
            'COUNT(*) as registrations,
             COALESCE(SUM(CASE WHEN is_active = 1 AND activated_at IS NOT NULL THEN 1 ELSE 0 END), 0) as activated,
             COALESCE(SUM(CASE WHEN is_active = 1 AND retained_at IS NOT NULL THEN 1 ELSE 0 END), 0) as retained,
             COALESCE(SUM(CASE WHEN is_active = 1 AND paid_order = 1 THEN 1 ELSE 0 END), 0) as paying,
             COALESCE(SUM(CASE WHEN is_active = 1 AND email_verified_at IS NOT NULL AND qualification_status = \'qualified\' THEN 1 ELSE 0 END), 0) as qualified'
        )->first();
        return array_map('intval', (array) $result);
    }

    public function reviewApplication(AmbassadorApplication $application, User $actor, string $status, string $note): void
    {
        abort_unless($actor->isSuperAdmin(), 403);
        DB::transaction(function () use ($application, $actor, $status, $note): void {
            $locked = AmbassadorApplication::whereKey($application->id)->lockForUpdate()->firstOrFail();
            $allowed = ['pending' => ['approved', 'rejected'], 'approved' => ['suspended'], 'suspended' => ['approved'], 'rejected' => [], 'withdrawn' => []];
            if (! in_array($status, $allowed[$locked->status] ?? [], true)) {
                throw ValidationException::withMessages(['status' => 'This application has changed. Refresh before reviewing it.']);
            }
            $old = $locked->status;
            $locked->forceFill(['status' => $status, 'review_note' => $note, 'reviewed_by' => $actor->id, 'reviewed_at' => now()])->save();
            AuditLog::log('ambassador.application.'.$status, $actor->id, AmbassadorApplication::class, $locked->id, $old, ['status' => $status, 'note' => $note]);
        });
    }

    public function submitReport(User $user, array $data, ?UploadedFile $evidence = null): void
    {
        $stored = null;
        try {
            DB::transaction(function () use ($user, $data, $evidence, &$stored): void {
                $application = AmbassadorApplication::where('user_id', $user->id)->lockForUpdate()->firstOrFail();
                abort_unless($user->is_active && $application->status === 'approved', 403);
                // A retried submission returns the original report and stores nothing new.
                if ($application->reports()->where('submission_key', $data['submission_key'])->exists()) return;
                if ($evidence) {
                    $mime = (string) $evidence->getMimeType();
                    $extension = AmbassadorReport::EVIDENCE_MIMES[$mime] ?? null;
                    if (! $extension) throw ValidationException::withMessages(['evidence_file' => 'Upload a JPG, PNG, WebP or PDF file.']);
                    // Random name on the private disk: no user-controlled path segments.
                    $stored = $evidence->storeAs('ambassador-evidence/'.$application->uuid, Str::random(40).'.'.$extension, 'local');
                    $data += ['evidence_path' => $stored, 'evidence_mime' => $mime, 'evidence_size' => (int) $evidence->getSize(),
                        'evidence_name' => Str::limit(preg_replace('/[^\pL\pN ._-]+/u', '', $evidence->getClientOriginalName()) ?: 'evidence', 150, '')];
                }
                $application->reports()->create($data);
            });
        } catch (\Throwable $exception) {
            if ($stored) Storage::disk('local')->delete($stored);
            throw $exception;
        }
    }

    public function reviewReport(AmbassadorReport $report, User $actor, string $status, string $note): void
    {
        abort_unless($actor->isSuperAdmin(), 403);
        DB::transaction(function () use ($report, $actor, $status, $note): void {
            $locked = AmbassadorReport::whereKey($report->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === 'pending' && in_array($status, ['approved', 'rejected'], true), 409);
            $locked->forceFill(['status' => $status, 'review_note' => $note, 'reviewed_by' => $actor->id, 'reviewed_at' => now()])->save();
            AuditLog::log('ambassador.report.'.$status, $actor->id, AmbassadorReport::class, $locked->id, 'pending', ['status' => $status, 'note' => $note]);
        });
    }

    public function award(AmbassadorApplication $application, User $actor, array $data): AmbassadorReward
    {
        abort_unless($actor->isSuperAdmin(), 403);
        $data['referral_id'] = ! empty($data['referral_id']) ? (int) $data['referral_id'] : null;
        return DB::transaction(function () use ($application, $actor, $data): AmbassadorReward {
            $locked = AmbassadorApplication::whereKey($application->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === 'approved', 409, 'Only approved ambassadors can receive new awards.');
            $reward = $locked->rewards()->firstOrCreate(['award_key' => $data['award_key']], $data + ['issued_by' => $actor->id]);
            if ($reward->wasRecentlyCreated) {
                if ($reward->kind === 'performance_bonus' || $reward->referral_id) {
                    app(AmbassadorQualificationService::class)->assertRewardEligible($locked->id, $reward->referral_id, $reward->id);
                }
                AuditLog::log('ambassador.reward.awarded', $actor->id, AmbassadorReward::class, $reward->id, null, $data);
            } else {
                foreach ($data as $key => $value) {
                    if ((string) $reward->{$key} !== (string) $value) {
                        throw ValidationException::withMessages(['award_key' => 'This award reference already exists with different details. Use the existing award or a new reference.']);
                    }
                }
            }
            return $reward;
        });
    }

    public function fulfil(AmbassadorReward $reward, User $actor, string $reference): void
    {
        abort_unless($actor->isSuperAdmin(), 403);
        DB::transaction(function () use ($reward, $actor, $reference): void {
            $application = AmbassadorApplication::whereKey($reward->ambassador_application_id)->lockForUpdate()->firstOrFail();
            $locked = AmbassadorReward::whereKey($reward->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === 'fulfilled') return;
            abort_unless($locked->status === 'pending' && $application->status === 'approved', 409, 'This award cannot be fulfilled in its current state.');
            $recipient = User::withoutGlobalScopes()->findOrFail($application->user_id);
            abort_unless($recipient->is_active, 409, 'The recipient account is inactive.');
            if ($locked->kind === 'performance_bonus' || $locked->referral_id) {
                app(AmbassadorQualificationService::class)->assertRewardEligible($application->id, $locked->referral_id, $locked->id);
            }
            if ($locked->kind === 'wallet_credit') {
                $wallets = app(WalletService::class);
                $wallet = $wallets->account($recipient);
                if ($wallet->currency !== $locked->currency) {
                    throw ValidationException::withMessages(['currency' => 'The award currency must match the recipient wallet. Cancel this award and issue one in the correct currency.']);
                }
                $entry = $wallets->creditSpending($recipient, $locked->amount_minor, 'ambassador_reward', reference: $locked,
                    description: $locked->title, metadata: ['issued_by' => $locked->issued_by, 'fulfilled_by' => $actor->id]);
                $reference = 'wallet-entry:'.$entry->id;
            } elseif (trim($reference) === '') {
                throw ValidationException::withMessages(['fulfilment_reference' => 'Record the payment or delivery reference before marking this award fulfilled.']);
            }
            $locked->update(['status' => 'fulfilled', 'fulfilled_by' => $actor->id, 'fulfilled_at' => now(), 'fulfilment_reference' => $reference]);
            AuditLog::log('ambassador.reward.fulfilled', $actor->id, AmbassadorReward::class, $locked->id, 'pending', ['reference' => $reference]);
        }, 3);
    }

    public function cancelReward(AmbassadorReward $reward, User $actor): void
    {
        abort_unless($actor->isSuperAdmin(), 403);
        DB::transaction(function () use ($reward, $actor): void {
            AmbassadorApplication::whereKey($reward->ambassador_application_id)->lockForUpdate()->firstOrFail();
            $locked = AmbassadorReward::whereKey($reward->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === 'pending', 409, 'Only pending awards can be cancelled.');
            $locked->update(['status' => 'cancelled']);
            AuditLog::log('ambassador.reward.cancelled', $actor->id, AmbassadorReward::class, $locked->id, 'pending', 'cancelled');
        });
    }
}
