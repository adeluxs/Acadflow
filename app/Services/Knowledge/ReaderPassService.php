<?php

declare(strict_types=1);

namespace App\Services\Knowledge;

use App\Models\KnowledgePublication;
use App\Models\KnowledgeReaderView;
use App\Models\User;
use App\Services\Commerce\PackageAccessService;
use App\Services\Commerce\PricingCatalogService;
use Illuminate\Contracts\Session\Session;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Metered reading: a few free reads a month, a friendly subscribe pop-up once
 * the reader is clearly enjoying it, then the Reader Pass for everything else.
 * Only free-access, published content is metered; premium items keep their own
 * purchase, institution content keeps its own rules.
 */
class ReaderPassService
{
    /** @var array<int,bool> */
    private array $passCache = [];

    public function __construct(
        private readonly ReadEarnSettings $settings,
        private readonly PackageAccessService $packages,
        private readonly PricingCatalogService $pricing,
    ) {}

    public function period(): string
    {
        return now()->format('Y-m');
    }

    public function hasPass(User $user): bool
    {
        if (! array_key_exists($user->id, $this->passCache)) {
            $state = $this->packages->status($user, ReadEarnSettings::PASS_FEATURE)['state'];
            // "free" means no active price is configured, so nothing can be sold: never block.
            $this->passCache[$user->id] = in_array($state, ['active', 'trial', 'grace', 'sponsored', 'admin', 'free'], true);
        }

        return $this->passCache[$user->id];
    }

    /** @return array{monthly_minor:?int,semester_minor:?int} */
    public function prices(): array
    {
        $rule = $this->pricing->featureRule(ReadEarnSettings::PASS_FEATURE);
        if (! $rule) {
            return ['monthly_minor' => null, 'semester_minor' => null];
        }
        $offers = $this->packages->offers($rule, ReadEarnSettings::PASS_FEATURE);

        return ['monthly_minor' => $offers['monthly']['amount_minor'] ?? null, 'semester_minor' => $offers['semester']['amount_minor'] ?? null];
    }

    /**
     * Records the open and decides whether the content is shown.
     *
     * @return array{applies:bool,locked:bool,prompt:bool,guest:bool,has_pass:bool,reads_used:int,free_reads:int}
     */
    public function gate(?User $user, KnowledgePublication $publication, Session $session): array
    {
        $result = ['applies' => false, 'locked' => false, 'prompt' => false, 'guest' => $user === null, 'has_pass' => false, 'reads_used' => 0, 'free_reads' => 0];
        if (! $this->settings->moduleEnabled($user?->university_id)) {
            return $result;
        }

        $metered = $this->settings->bool('knowledge_reader_pass_enabled')
            && $publication->access_type === 'free'
            && $publication->status === 'published';

        if (! $user) {
            if (! $metered) {
                return $result;
            }
            $free = $this->settings->int('knowledge_reader_pass_guest_free_reads');
            $reads = array_values(array_unique(array_map('intval', (array) $session->get('knowledge_guest_reads', []))));
            $result = ['applies' => true, 'free_reads' => $free, 'reads_used' => count($reads)] + $result;
            if (in_array($publication->id, $reads, true)) {
                return $result;
            }
            if (count($reads) >= $free) {
                return ['locked' => true] + $result;
            }
            $reads[] = $publication->id;
            $session->put('knowledge_guest_reads', $reads);

            return ['reads_used' => count($reads)] + $result;
        }

        $exempt = $publication->creator_id === $user->id || $user->isAdmin();
        if (! $metered || $exempt) {
            // Still remember the open: Read & Earn uses it to check reading time.
            $this->touch($user, $publication, false);

            return $result;
        }

        $hasPass = $this->hasPass($user);
        $free = $this->settings->int('knowledge_reader_pass_free_reads');
        $period = $this->period();
        $existing = KnowledgeReaderView::query()->where('user_id', $user->id)->where('knowledge_publication_id', $publication->id)->where('period', $period)->first();
        $used = (int) KnowledgeReaderView::query()->where('user_id', $user->id)->where('period', $period)->where('metered', true)->count();
        $result = ['applies' => true, 'has_pass' => $hasPass, 'free_reads' => $free, 'reads_used' => $used] + $result;

        if ($existing) {
            $existing->forceFill(['last_opened_at' => now()])->save();
            $locked = ! $hasPass && ! $existing->metered && $used >= $free;
            if (! $locked && ! $hasPass && ! $existing->metered) {
                // Opened before the paywall was switched on: this open now uses a free read.
                $existing->forceFill(['metered' => true])->save();
                $result['reads_used'] = ++$used;
            }

            return ['locked' => $locked, 'prompt' => ! $locked && $this->shouldPrompt($hasPass, $used, $session)] + $result;
        }

        if (! $hasPass && $used >= $free) {
            return ['locked' => true] + $result;
        }

        $this->touch($user, $publication, ! $hasPass);
        if (! $hasPass) {
            $result['reads_used'] = ++$used;
        }

        return ['prompt' => $this->shouldPrompt($hasPass, $used, $session)] + $result;
    }

    private function shouldPrompt(bool $hasPass, int $used, Session $session): bool
    {
        if ($hasPass || $used < max(1, $this->settings->int('knowledge_reader_pass_prompt_after'))) {
            return false;
        }
        $key = 'knowledge_pass_prompted.'.$this->period();
        if ($session->get($key)) {
            return false;
        }
        $session->put($key, true);

        return true;
    }

    private function touch(User $user, KnowledgePublication $publication, bool $metered): void
    {
        $period = $this->period();
        $view = KnowledgeReaderView::query()->where('user_id', $user->id)->where('knowledge_publication_id', $publication->id)->where('period', $period)->first();
        if ($view) {
            $view->forceFill(['last_opened_at' => now()])->save();

            return;
        }
        try {
            KnowledgeReaderView::create([
                'user_id' => $user->id, 'knowledge_publication_id' => $publication->id, 'period' => $period,
                'metered' => $metered, 'first_opened_at' => now(), 'last_opened_at' => now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            // A parallel tab recorded the same open first.
        }
    }
}
