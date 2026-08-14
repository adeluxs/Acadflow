<?php

namespace App\Services;

use App\Enums\NotificationType;
use App\Models\EngagementComment;
use App\Models\EngagementMention;
use App\Models\EngagementReaction;
use App\Models\EngagementReport;
use App\Models\EngagementShare;
use App\Models\EngagementSubscription;
use App\Models\EngagementThread;
use App\Models\KnowledgeEvent;
use App\Models\KnowledgeFollow;
use App\Models\KnowledgePublication;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EngagementService
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function threadFor(Model $target, ?int $universityId = null, string $visibility = 'private', ?string $title = null): EngagementThread
    {
        return EngagementThread::firstOrCreate(
            ['target_type' => $target->getMorphClass(), 'target_id' => $target->getKey()],
            [
                'university_id' => $universityId,
                'title' => $title,
                'visibility' => $visibility,
                'status' => 'open',
            ]
        );
    }

    public function comment(Model $target, User $author, string $body, array $attributes = []): EngagementComment
    {
        $thread = $this->threadFor(
            $target,
            $attributes['university_id'] ?? $author->university_id,
            $attributes['visibility'] ?? 'private',
            $attributes['thread_title'] ?? null,
        );

        abort_if($thread->is_locked || $thread->status !== 'open', 423, 'This discussion is closed.');

        $comment = DB::transaction(function () use ($thread, $author, $body, $attributes) {
            $comment = EngagementComment::create([
                'engagement_thread_id' => $thread->id,
                'user_id' => $author->id,
                'parent_id' => $attributes['parent_id'] ?? null,
                'comment_type' => $attributes['comment_type'] ?? 'comment',
                'section_key' => $attributes['section_key'] ?? null,
                'body' => trim($body),
                'status' => 'visible',
                'metadata' => $attributes['metadata'] ?? null,
            ]);

            $this->processMentions($comment, $author);
            $this->incrementCounter($thread, 'comment_count');

            return $comment;
        });

        $this->notifySubscribers($thread, $author, $comment);

        return $comment->fresh(['user', 'replies.user']);
    }

    public function resolve(EngagementComment $comment, User $actor): EngagementComment
    {
        $comment->update(['status' => 'resolved', 'resolved_by' => $actor->id, 'resolved_at' => now()]);

        return $comment->fresh();
    }

    public function react(Model $target, User $user, string $reaction = 'like'): bool
    {
        $attributes = [
            'user_id' => $user->id,
            'reactable_type' => $target->getMorphClass(),
            'reactable_id' => $target->getKey(),
            'reaction' => $reaction,
        ];

        $existing = EngagementReaction::query()->where($attributes)->first();
        if ($existing) {
            $existing->delete();
            $this->recordKnowledgeEvent($target, $user, 'reaction_removed', ['reaction' => $reaction]);
            return false;
        }

        EngagementReaction::create($attributes + ['created_at' => now()]);
        $this->recordKnowledgeEvent($target, $user, 'reaction', ['reaction' => $reaction]);

        return true;
    }

    public function report(Model $target, User $reporter, string $reason, ?string $details = null): EngagementReport
    {
        return EngagementReport::create([
            'university_id' => $reporter->university_id,
            'reporter_id' => $reporter->id,
            'reportable_type' => $target->getMorphClass(),
            'reportable_id' => $target->getKey(),
            'reason' => $reason,
            'details' => $details,
            'status' => 'open',
        ]);
    }

    public function share(Model $target, ?User $user, string $channel = 'copy_link', array $metadata = []): EngagementShare
    {
        $share = EngagementShare::create([
            'user_id' => $user?->id,
            'shareable_type' => $target->getMorphClass(),
            'shareable_id' => $target->getKey(),
            'channel' => $channel,
            'metadata' => $metadata,
            'created_at' => now(),
        ]);
        if ($target instanceof KnowledgePublication) {
            $target->increment('share_count');
            $this->recordKnowledgeEvent($target, $user, 'share', ['channel' => $channel]);
        }

        return $share;
    }

    public function ensureSubscribed(Model $target, User $user, string $frequency = 'immediate'): EngagementSubscription
    {
        return EngagementSubscription::firstOrCreate([
            'user_id' => $user->id,
            'subscribable_type' => $target->getMorphClass(),
            'subscribable_id' => $target->getKey(),
        ], ['frequency' => $frequency, 'is_muted' => false]);
    }

    public function subscribe(Model $target, User $user, string $frequency = 'immediate'): bool
    {
        $attributes = [
            'user_id' => $user->id,
            'subscribable_type' => $target->getMorphClass(),
            'subscribable_id' => $target->getKey(),
        ];
        $existing = EngagementSubscription::query()->where($attributes)->first();
        if ($existing) {
            $existing->delete();
            if ($target instanceof KnowledgePublication) {
                KnowledgeFollow::query()->where('follower_id', $user->id)->where('target_type', 'publication')->where('target_id', $target->id)->delete();
            }
            return false;
        }

        EngagementSubscription::create($attributes + ['frequency' => $frequency]);
        if ($target instanceof KnowledgePublication) {
            KnowledgeFollow::firstOrCreate([
                'follower_id' => $user->id,
                'target_type' => 'publication',
                'target_id' => $target->id,
            ], ['created_at' => now()]);
        }

        return true;
    }

    public function commentsFor(Model $target, int $perPage = 30)
    {
        $thread = EngagementThread::query()
            ->where('target_type', $target->getMorphClass())
            ->where('target_id', $target->getKey())
            ->first();

        return $thread
            ? EngagementComment::query()->with(['user', 'replies' => fn ($query) => $query->with('user')->withCount('reactions')])->withCount('reactions')->where('engagement_thread_id', $thread->id)->whereNull('parent_id')->where('status', '!=', 'hidden')->oldest()->paginate($perPage)
            : EngagementComment::query()->whereRaw('1 = 0')->paginate($perPage);
    }

    private function processMentions(EngagementComment $comment, User $author): void
    {
        preg_match_all('/@\[user:(\d+)\]|@([A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,})/', $comment->body, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $mentionedQuery = User::query()->where('university_id', $author->university_id);
            $mentioned = ! empty($match[1])
                ? $mentionedQuery->whereKey((int) $match[1])->first()
                : $mentionedQuery->where('email', $match[2] ?? '')->first();
            if (! $mentioned || $mentioned->id === $author->id) {
                continue;
            }

            EngagementMention::firstOrCreate([
                'mentioned_user_id' => $mentioned->id,
                'source_type' => EngagementComment::class,
                'source_id' => $comment->id,
            ], [
                'mentioned_by' => $author->id,
                'context' => Str::limit(strip_tags($comment->body), 180),
                'created_at' => now(),
            ]);

            $this->notifications->send(
                $mentioned,
                NotificationType::SYSTEM_ANNOUNCEMENT,
                'You were mentioned',
                $author->full_name.' mentioned you in a discussion.',
                ['engagement_comment_uuid' => $comment->uuid]
            );
        }
    }

    private function notifySubscribers(EngagementThread $thread, User $author, EngagementComment $comment): void
    {
        EngagementSubscription::query()
            ->where('subscribable_type', $thread->target_type)
            ->where('subscribable_id', $thread->target_id)
            ->where('is_muted', false)
            ->where('user_id', '!=', $author->id)
            ->with('user')
            ->chunkById(100, function ($subscriptions) use ($author, $comment): void {
                foreach ($subscriptions as $subscription) {
                    if ($subscription->user) {
                        $this->notifications->send(
                            $subscription->user,
                            NotificationType::SYSTEM_ANNOUNCEMENT,
                            'New discussion activity',
                            $author->full_name.' added a comment.',
                            ['engagement_comment_uuid' => $comment->uuid]
                        );
                    }
                }
            });
    }

    private function incrementCounter(EngagementThread $thread, string $counter): void
    {
        if ($thread->target_type === KnowledgePublication::class) {
            KnowledgePublication::whereKey($thread->target_id)->increment($counter);
        }
    }

    private function recordKnowledgeEvent(Model $target, ?User $user, string $event, array $metadata = []): void
    {
        if ($target instanceof KnowledgePublication) {
            KnowledgeEvent::create([
                'knowledge_publication_id' => $target->id,
                'user_id' => $user?->id,
                'event_type' => $event,
                'metadata' => $metadata,
                'created_at' => now(),
            ]);
        }
    }
}
