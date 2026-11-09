<?php

declare(strict_types=1);

namespace App\Services\Knowledge;

use App\Jobs\SendKnowledgeDigestBatch;
use App\Models\KnowledgeFollow;
use App\Models\KnowledgePublication;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\SettingService;
use Illuminate\Support\Str;

/**
 * Knowledge Hub alerts through the standard notification pipeline, so each one
 * is in-app, email and push, follows every user's notification preferences,
 * quiet hours and the admin's per-event channel switches.
 */
class KnowledgeNotificationService
{
    public const FOLLOWED = 'knowledge_new_from_followed';
    public const DIGEST = 'knowledge_weekly_digest';
    public const CREATOR_TARGETS = ['creator', User::class];

    public function __construct(private readonly ReadEarnSettings $settings, private readonly NotificationService $notifications) {}

    public static function followerCount(int $creatorId): int
    {
        return KnowledgeFollow::query()->whereIn('target_type', self::CREATOR_TARGETS)->where('target_id', $creatorId)->count();
    }

    /** Alerts the creator's followers about a newly published, visible publication. Returns how many were sent. */
    public function notifyFollowers(KnowledgePublication $publication): int
    {
        if (! $this->settings->bool('knowledge_notify_followers_enabled')) {
            return 0;
        }
        $publication = KnowledgePublication::withoutGlobalScopes()->with('creator:id,first_name,last_name')->find($publication->id);
        if (! $publication || $publication->status !== 'published' || ! in_array($publication->visibility, ['public', 'institution'], true) || ! $publication->creator) {
            return 0;
        }

        $creatorName = trim($publication->creator->first_name.' '.$publication->creator->last_name) ?: 'A creator you follow';
        $title = $creatorName.' published something new';
        $excerpt = Str::limit(trim((string) $publication->excerpt), 140);
        $message = '"'.$publication->title.'"'.($excerpt !== '' ? ' · '.$excerpt : '');
        $url = route('knowledge.show', $publication);
        $sent = 0;

        KnowledgeFollow::query()->whereIn('target_type', self::CREATOR_TARGETS)->where('target_id', $publication->creator_id)
            ->select(['id', 'follower_id'])
            ->chunkById(500, function ($follows) use ($publication, $title, $message, $url, &$sent): void {
                $followers = User::withoutGlobalScopes()->whereIn('id', $follows->pluck('follower_id'))->where('is_active', true)
                    ->when($publication->visibility === 'institution', fn ($q) => $q->where('university_id', $publication->university_id))
                    ->get();
                foreach ($followers as $follower) {
                    if ($follower->id === $publication->creator_id) {
                        continue;
                    }
                    $this->notifications->send($follower, self::FOLLOWED, $title, $message, [
                        'url' => $url, 'publication_id' => $publication->id, 'event_key' => 'knowledge-followed:'.$publication->id,
                    ]);
                    $sent++;
                }
            });

        return $sent;
    }

    /**
     * Queues the weekly roundup when it is due (or always with $force).
     *
     * @return array{status:string,week?:string,publications?:int,batches?:int}
     */
    public function dispatchDigest(bool $force = false): array
    {
        if (! $this->settings->bool('knowledge_digest_enabled')) {
            return ['status' => 'disabled'];
        }
        $now = now();
        if (! $force && ($now->dayOfWeekIso !== $this->settings->int('knowledge_digest_day') || $now->hour !== $this->settings->int('knowledge_digest_hour'))) {
            return ['status' => 'not_due'];
        }
        $week = $now->format('o-\WW');
        if (! $force && SettingService::getGlobal('knowledge_digest_last_week') === $week) {
            return ['status' => 'already_sent', 'week' => $week];
        }

        $recent = KnowledgePublication::withoutGlobalScopes()->where('status', 'published')->where('visibility', 'public')
            ->where('published_at', '>=', $now->copy()->subDays(7));
        $total = (clone $recent)->count();
        if ($total < max(1, $this->settings->int('knowledge_digest_min_new'))) {
            return ['status' => 'too_few', 'publications' => $total];
        }
        $top = (clone $recent)->orderByDesc('view_count')->orderByDesc('published_at')->limit(max(1, $this->settings->int('knowledge_digest_items')))->pluck('title')->all();

        $quoted = array_map(fn (string $t) => '"'.Str::limit($t, 70).'"', $top);
        $more = $total - count($top);
        $message = 'New this week: '.implode(', ', $quoted).($more > 0 ? ' and '.$more.' more.' : '.').' Read, learn and earn on AcadSyntra.';
        $title = $total.' new guide'.($total === 1 ? '' : 's').' on the Knowledge Hub this week';

        SettingService::set('knowledge_digest_last_week', $week, 'string');
        $batches = 0;
        User::withoutGlobalScopes()->where('is_active', true)->select('id')->chunkById(500, function ($users) use ($week, $title, $message, &$batches): void {
            SendKnowledgeDigestBatch::dispatch($users->pluck('id')->all(), $week, $title, $message);
            $batches++;
        });

        return ['status' => 'queued', 'week' => $week, 'publications' => $total, 'batches' => $batches];
    }

    /** @param list<int> $userIds */
    public function sendDigestTo(array $userIds, string $week, string $title, string $message): void
    {
        $url = route('knowledge.index');
        User::withoutGlobalScopes()->whereIn('id', $userIds)->where('is_active', true)->get()->each(function (User $user) use ($week, $title, $message, $url): void {
            $this->notifications->send($user, self::DIGEST, $title, $message, ['url' => $url, 'event_key' => 'knowledge-digest:'.$week]);
        });
    }
}
