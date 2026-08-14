<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('discussions') || ! Schema::hasTable('engagement_threads')) return;

        DB::table('discussions')->orderBy('id')->chunkById(100, function ($discussions): void {
            foreach ($discussions as $discussion) {
                $universityId = DB::table('courses')->join('departments', 'courses.department_id', '=', 'departments.id')->join('faculties', 'departments.faculty_id', '=', 'faculties.id')->where('courses.id', $discussion->course_id)->value('faculties.university_id');
                $threadId = DB::table('engagement_threads')->where('target_type', App\Models\Discussion::class)->where('target_id', $discussion->id)->value('id');
                if (! $threadId) {
                    $threadId = DB::table('engagement_threads')->insertGetId([
                        'uuid' => (string) Str::uuid(), 'university_id' => $universityId,
                        'target_type' => App\Models\Discussion::class, 'target_id' => $discussion->id,
                        'title' => $discussion->title, 'visibility' => 'institution',
                        'status' => $discussion->status === 'closed' ? 'closed' : 'open', 'is_locked' => in_array($discussion->status, ['closed','archived'], true),
                        'settings' => json_encode(['migrated_from' => 'course_discussions']), 'created_at' => $discussion->created_at, 'updated_at' => $discussion->updated_at,
                    ]);
                }

                DB::table('engagement_subscriptions')->updateOrInsert([
                    'user_id' => $discussion->user_id, 'subscribable_type' => App\Models\Discussion::class, 'subscribable_id' => $discussion->id,
                ], ['frequency' => 'immediate', 'is_muted' => false, 'created_at' => $discussion->created_at, 'updated_at' => $discussion->updated_at]);

                $map = [];
                $replies = DB::table('discussion_replies')->where('discussion_id', $discussion->id)->whereNull('deleted_at')->orderBy('id')->get();
                foreach ($replies as $reply) {
                    $existing = DB::table('engagement_comments')->where('engagement_thread_id', $threadId)->where('metadata->legacy_discussion_reply_id', $reply->id)->value('id');
                    if (! $existing) {
                        $existing = DB::table('engagement_comments')->insertGetId([
                            'uuid' => (string) Str::uuid(), 'engagement_thread_id' => $threadId, 'user_id' => $reply->user_id,
                            'parent_id' => $reply->parent_reply_id ? ($map[$reply->parent_reply_id] ?? null) : null,
                            'comment_type' => $reply->type, 'body' => $reply->content, 'status' => 'visible', 'is_pinned' => false,
                            'is_verified_response' => (bool) $reply->is_accepted, 'resolved_at' => $reply->accepted_at,
                            'metadata' => json_encode(['legacy_discussion_reply_id' => $reply->id, 'legacy_uuid' => $reply->uuid]),
                            'created_at' => $reply->created_at, 'updated_at' => $reply->updated_at,
                        ]);
                    }
                    $map[$reply->id] = $existing;
                }
            }
        });
    }

    public function down(): void
    {
        // Shared engagement may contain new production activity after migration;
        // rollback intentionally preserves it rather than destructively deleting comments.
    }
};
