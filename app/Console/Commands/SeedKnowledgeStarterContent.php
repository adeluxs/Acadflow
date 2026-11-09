<?php

namespace App\Console\Commands;

use App\Jobs\IndexSearchableContent;
use App\Jobs\RecalculateReputation;
use App\Models\KnowledgeCategory;
use App\Models\KnowledgePublication;
use App\Models\KnowledgePublicationType;
use App\Models\KnowledgeQuizQuestion;
use App\Models\User;
use App\Services\Knowledge\PublicationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Loads AcadSyntra's original starter guides (database/data/knowledge-starter)
 * into the Knowledge Hub with their Read & Earn questions. Safe to run again:
 * a title the author has already published is skipped, never duplicated.
 */
class SeedKnowledgeStarterContent extends Command
{
    protected $signature = 'knowledge:seed-starter
        {--author= : Email of the account to publish as (default: the first Super Admin)}
        {--draft : Create drafts for review instead of publishing}
        {--dry-run : Show what would be created without saving anything}';

    protected $description = 'Seed the Knowledge Hub with AcadSyntra starter guides and their quiz questions';

    /** @return list<array<string,mixed>> */
    public static function items(): array
    {
        $items = [];
        foreach (glob(database_path('data/knowledge-starter/*.php')) ?: [] as $file) {
            foreach ((array) require $file as $item) {
                $items[] = $item;
            }
        }

        return $items;
    }

    public function handle(PublicationService $publications): int
    {
        $email = $this->option('author');
        $author = $email
            ? User::withoutGlobalScopes()->where('email', $email)->first()
            : User::withoutGlobalScopes()->where('role', 'super_admin')->where('is_active', true)->orderBy('id')->first();
        if (! $author) {
            $this->error($email ? "No account found for {$email}." : 'No active Super Admin found. Pass --author=email.');

            return self::FAILURE;
        }

        $categories = KnowledgeCategory::query()->whereNull('university_id')->pluck('id', 'name');
        $types = KnowledgePublicationType::query()->where('is_active', true)->pluck('id', 'slug');
        $publish = ! $this->option('draft');
        $dry = (bool) $this->option('dry-run');
        Auth::setUser($author);

        $created = 0;
        $skipped = 0;
        foreach (self::items() as $item) {
            $categoryId = $categories[$item['category']] ?? null;
            $typeId = $types[$item['type']] ?? null;
            if (! $categoryId || ! $typeId) {
                $this->warn("Skipped \"{$item['title']}\": category or type not found ({$item['category']} / {$item['type']}).");
                $skipped++;

                continue;
            }
            if (KnowledgePublication::withoutGlobalScopes()->where('creator_id', $author->id)->where('title', $item['title'])->exists()) {
                $this->line("Already there: {$item['title']}");
                $skipped++;

                continue;
            }
            if ($dry) {
                $this->line("Would create: [{$item['category']}] {$item['title']} (".count($item['questions']).' questions)');
                $created++;

                continue;
            }

            $this->withRetry(fn () => DB::transaction(function () use ($publications, $author, $item, $categoryId, $typeId, $publish): void {
                $publication = $publications->createDraft([
                    'title' => $item['title'],
                    'body' => $item['body'],
                    'excerpt' => $item['excerpt'],
                    'category_id' => $categoryId,
                    'publication_type_id' => $typeId,
                    'access_type' => 'free',
                    'visibility' => 'public',
                    'license_type' => 'all_rights_reserved',
                    'ownership_declaration' => true,
                    'tags' => $item['tags'] ?? [],
                    'copyright' => 'Original content by AcadSyntra',
                ], $author);

                foreach (array_values($item['questions']) as $position => $question) {
                    KnowledgeQuizQuestion::create([
                        'knowledge_publication_id' => $publication->id,
                        'question' => $question['q'],
                        'options' => array_values($question['options']),
                        'correct_option' => (int) $question['answer'],
                        'explanation' => $question['explain'] ?? null,
                        'sort_order' => $position,
                        'is_active' => true,
                        'created_by' => $author->id,
                    ]);
                }

                if ($publish) {
                    // Same state changes as a moderator approval, without 35 "approved" notifications.
                    $publication->update(['status' => 'published', 'moderated_by' => $author->id, 'moderation_note' => 'AcadSyntra starter content', 'published_at' => now()]);
                    $publication->document?->update(['status' => 'published', 'visibility' => 'public']);
                    IndexSearchableContent::dispatch(KnowledgePublication::class, $publication->id)->onQueue('indexing');
                }
            }));
            $this->info(($publish ? 'Published' : 'Draft created').": {$item['title']}");
            $created++;
        }

        if ($publish && $created > 0 && ! $dry) {
            RecalculateReputation::dispatch($author->id)->onQueue('analytics');
        }
        $this->newLine();
        $this->info(($dry ? 'Would create ' : 'Created ').$created.', skipped '.$skipped.'. Author: '.$author->email.'.');

        return self::SUCCESS;
    }

    /**
     * A local SQLite database can be busy for a moment while the queue worker or the web server
     * writes. Each guide is one transaction, so a busy guide is simply tried again.
     */
    private function withRetry(callable $work, int $attempts = 5): void
    {
        for ($try = 1; ; $try++) {
            try {
                $work();

                return;
            } catch (\Illuminate\Database\QueryException|\PDOException $e) {
                $busy = $e instanceof \Illuminate\Database\DeadlockException || str_contains(strtolower($e->getMessage()), 'database is locked');
                if (! $busy || $try >= $attempts) {
                    throw $e;
                }
                $this->warn("Database busy, retrying ({$try}/".($attempts - 1).')...');
                usleep(1000000 * $try);
            }
        }
    }
}
