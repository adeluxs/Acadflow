<?php

declare(strict_types=1);

namespace App\Services\Knowledge;

use App\Services\FeatureAccessService;
use App\Services\SettingService;
use App\Support\Money;

/**
 * Every Reader Pass / Read & Earn / reward-pool rule in one registry. The admin
 * page renders straight from DEFINITIONS, so a rule added here is automatically
 * editable in Admin > Knowledge Hub > Read & Earn.
 */
class ReadEarnSettings
{
    public const FEATURE = 'knowledge_read_to_earn';
    public const PASS_FEATURE = 'knowledge_reader_pass';

    /** key => [type, default, label, help, group] ; money values are naira strings. */
    public const DEFINITIONS = [
        // Reader Pass (subscription) and the metered paywall.
        'knowledge_reader_pass_enabled' => ['boolean', false, 'Require a Reader Pass after the free reads', 'When off, everyone reads free Knowledge Hub content without limit. The pass price is edited in Admin > Monetization (feature.knowledge_reader_pass).', 'pass'],
        'knowledge_reader_pass_free_reads' => ['integer', 3, 'Free reads each month', 'Different publications a signed-in reader can open each month before the pass is required.', 'pass'],
        'knowledge_reader_pass_prompt_after' => ['integer', 2, 'Show the subscribe pop-up after this many reads', 'The pop-up appears once per session after the reader has opened this many publications in the month.', 'pass'],
        'knowledge_reader_pass_guest_free_reads' => ['integer', 2, 'Free reads for visitors who are not signed in', 'After this, visitors are asked to sign in. Counted per browser session.', 'pass'],

        // Read & Earn (quiz-verified reading).
        'knowledge_read_earn_requires_pass' => ['boolean', true, 'Only Reader Pass holders earn', 'Paying readers are far less likely to be fake accounts.', 'earn'],
        'knowledge_read_earn_hold_days' => ['integer', 7, 'Save points for readers without a Reader Pass (days)', 'Everyone can answer a guide\'s questions. Without the pass, points are saved this long and paid as soon as the reader gets the pass. 0 = no points without the pass.', 'earn'],
        'knowledge_read_earn_min_questions' => ['integer', 3, 'Questions needed before a publication can earn', 'Counts AI-generated and creator questions together.', 'earn'],

        // AI question banks.
        'knowledge_quiz_ai_enabled' => ['boolean', true, 'Write Read & Earn questions with AI', 'When a guide is published, AI writes a question bank from the guide so creators don\'t have to. Creators can still edit, remove or add questions. Uses the AI provider set in AI Settings (feature "knowledge_quiz_generator").', 'quiz'],
        'knowledge_quiz_bank_size' => ['integer', 15, 'Questions in each bank', 'Shorter guides get fewer (about one per 60 words, at least 6).', 'quiz'],
        'knowledge_quiz_per_reader' => ['integer', 5, 'Questions each reader answers', 'Picked at random from the bank, with the answer options shuffled, so readers get different quizzes.', 'quiz'],
        'knowledge_quiz_report_limit' => ['integer', 3, 'Reports before a question is pulled', 'Readers can report a wrong or unclear question. At this many reports it stops being asked and appears for review below.', 'quiz'],
        'knowledge_quiz_ai_requires_approval' => ['boolean', false, 'New AI questions need approval first', 'When on, AI questions stay hidden until the creator saves them on the questions page or an admin approves them.', 'quiz'],
        'knowledge_read_earn_pass_mark' => ['integer', 60, 'Pass mark (%)', 'Readers below this score earn nothing for that publication.', 'earn'],
        'knowledge_read_earn_points_per_read' => ['integer', 10, 'Points for a perfect score', 'Points scale with the score, e.g. 80% earns 8 of 10.', 'earn'],
        'knowledge_read_earn_reflection_bonus' => ['integer', 2, 'Bonus points for a written reflection', '"What did you learn and gain?" answer. Set 0 to turn the bonus off.', 'earn'],
        'knowledge_read_earn_reflection_min_words' => ['integer', 20, 'Minimum words for the reflection bonus', '', 'earn'],
        'knowledge_read_earn_max_wpm' => ['integer', 400, 'Fastest believable reading speed (words per minute)', 'Quizzes submitted faster than this earn nothing.', 'earn'],
        'knowledge_read_earn_min_seconds' => ['integer', 45, 'Minimum reading time (seconds)', 'Applies even to very short publications.', 'earn'],
        'knowledge_read_earn_daily_cap' => ['integer', 5, 'Earning reads per reader per day', '', 'earn'],
        'knowledge_read_earn_creator_daily_cap' => ['integer', 2, 'Earning reads per reader per creator per day', 'Stops friends farming one creator.', 'earn'],
        'knowledge_read_earn_max_accounts_per_ip' => ['integer', 3, 'Accounts per network per day before review', 'Further quizzes from the same network are held for admin review instead of paying.', 'earn'],
        'knowledge_read_earn_credit_per_point' => ['money', '1', 'Study credit per point (NGN)', 'Paid instantly as study credit (spend-only). Set 0 to give points only.', 'earn'],
        'knowledge_read_earn_monthly_credit_cap' => ['money', '500', 'Maximum study credit per reader per month (NGN)', '', 'earn'],

        // Monthly reader cash pool.
        'knowledge_reader_pool_amount' => ['money', '50000', 'Monthly reader pool (NGN)', 'Shared by eligible readers in proportion to their points. Your cost is never more than this.', 'reader_pool'],
        'knowledge_reader_pool_min_account_days' => ['integer', 14, 'Minimum account age (days)', '', 'reader_pool'],
        'knowledge_reader_pool_min_passed_reads' => ['integer', 10, 'Passed reads needed to unlock cash (all time)', 'The reading level a reader must reach before they can earn cash.', 'reader_pool'],
        'knowledge_reader_pool_min_creators' => ['integer', 3, 'Different creators read (all time)', '', 'reader_pool'],
        'knowledge_reader_pool_min_average' => ['integer', 60, 'Minimum average score in the month (%)', '', 'reader_pool'],
        'knowledge_reader_pool_max_share' => ['integer', 5, 'Most one reader can get (% of the pool)', 'Spreads the pool across more readers.', 'reader_pool'],

        // Monthly creator cash pool.
        'knowledge_creator_pool_amount' => ['money', '50000', 'Monthly creator pool (NGN)', 'Shared by qualifying creators in proportion to verified reads (reads whose quiz was passed).', 'creator_pool'],
        'knowledge_creator_pool_min_verified_reads' => ['integer', 20, 'Verified reads needed in the month', 'The target a creator must reach before earning from the pool.', 'creator_pool'],
        'knowledge_creator_pool_min_followers' => ['integer', 0, 'Followers needed', '', 'creator_pool'],
        'knowledge_creator_pool_requires_approval' => ['boolean', true, 'Only approved monetized creators', 'Uses the existing creator monetization application.', 'creator_pool'],
        'knowledge_creator_pool_min_reader_days' => ['integer', 7, 'Reader account age for a read to count (days)', 'Reads from brand-new accounts do not count towards creators.', 'creator_pool'],
        'knowledge_creator_pool_max_share' => ['integer', 20, 'Most one creator can get (% of the pool)', '', 'creator_pool'],

        // Challenges.
        'knowledge_challenge_default_prize_pool' => ['money', '50000', 'Default prize pool for a new challenge (NGN)', 'Each challenge can still set its own prizes.', 'challenges'],
        'knowledge_challenge_popup_enabled' => ['boolean', true, 'Show the "challenges are live" pop-up', 'Shown once after login and then every so often (below) while a Reading or Writing Challenge is open or starting within 14 days. Never shown while someone is reading or writing a publication.', 'challenges'],
        'knowledge_challenge_popup_minutes' => ['integer', 60, 'Show the pop-up again after (minutes)', '', 'challenges'],
        'knowledge_challenge_prizes_enabled' => ['boolean', true, 'Let community challenge creators offer funded prizes', 'Creators pay the prize upfront; AcadSyntra holds it until winners are paid.', 'challenges'],
        'knowledge_challenge_prize_fee_percent' => ['integer', 5, 'Fee on community challenge prizes (%)', 'Added on top of the prize when the creator funds it. Kept if unused prizes are refunded.', 'challenges'],
        'knowledge_challenge_prize_min' => ['money', '1000', 'Smallest prize a creator can offer (NGN)', '', 'challenges'],
        'knowledge_challenge_prize_auto_days' => ['integer', 7, 'Pay winners automatically this many days after results', 'Protects winners if a creator never presses "Pay winners". 0 turns this off.', 'challenges'],

        // Search engines and AI assistants.
        'seo_allow_ai_crawlers' => ['boolean', true, 'Let AI assistants read free guides', 'ChatGPT, Claude, Perplexity, Google AI and others may read and cite public free guides (and see /llms.txt). Premium guide text is never shown to anyone without access. Turn off to block AI crawlers in robots.txt.', 'seo'],

        // Knowledge Hub notifications (in-app, email and push; users can mute each in their notification settings).
        'knowledge_notify_followers_enabled' => ['boolean', true, 'Tell followers when a creator publishes', 'Sent once per new publication, in-app plus email and push. Email/push channels can be switched off per message type in Admin > Notifications (event "knowledge_new_from_followed").', 'notifications'],
        'knowledge_digest_enabled' => ['boolean', true, 'Send the weekly "New on the Knowledge Hub" roundup', 'One message per user per week instead of an alert for every post (event "knowledge_weekly_digest").', 'notifications'],
        'knowledge_digest_day' => ['integer', 7, 'Roundup day (1 = Monday … 7 = Sunday)', '', 'notifications'],
        'knowledge_digest_hour' => ['integer', 18, 'Roundup hour (0–23, server time)', '', 'notifications'],
        'knowledge_digest_items' => ['integer', 5, 'Guides listed in the roundup', 'The most-read new publications of the past 7 days.', 'notifications'],
        'knowledge_digest_min_new' => ['integer', 3, 'Skip the roundup if fewer new publications than this', 'Avoids sending a near-empty message.', 'notifications'],
    ];

    /** Extra validation for settings that have a fixed range. */
    public const LIMITS = [
        'knowledge_digest_day' => [1, 7],
        'knowledge_read_earn_hold_days' => [0, 90],
        'knowledge_quiz_bank_size' => [3, 30],
        'knowledge_challenge_popup_minutes' => [5, 1440],
        'knowledge_quiz_per_reader' => [1, 15],
        'knowledge_quiz_report_limit' => [1, 50],
        'knowledge_digest_hour' => [0, 23],
        'knowledge_digest_items' => [1, 20],
        'knowledge_challenge_prize_fee_percent' => [0, 50],
        'knowledge_challenge_prize_auto_days' => [0, 90],
        'knowledge_read_earn_pass_mark' => [0, 100],
        'knowledge_reader_pool_min_average' => [0, 100],
        'knowledge_reader_pool_max_share' => [1, 100],
        'knowledge_creator_pool_max_share' => [1, 100],
    ];

    public const GROUPS = [
        'pass' => 'Reader Pass and free reads',
        'earn' => 'Read & Earn rules',
        'quiz' => 'Read & Earn questions (AI question banks)',
        'reader_pool' => 'Monthly reader cash pool',
        'creator_pool' => 'Monthly creator cash pool',
        'challenges' => 'Challenges',
        'notifications' => 'Knowledge Hub notifications',
        'seo' => 'Search engines and AI assistants',
    ];

    /** @var array<string,mixed>|null */
    private ?array $values = null;

    public function moduleEnabled(?int $universityId = null): bool
    {
        return FeatureAccessService::effectiveStatus(self::FEATURE, $universityId) === FeatureAccessService::STATUS_ENABLED;
    }

    /** @return array<string,mixed> */
    public function all(): array
    {
        if ($this->values === null) {
            $defaults = array_map(fn (array $definition) => $definition[1], self::DEFINITIONS);
            $values = SettingService::getMany($defaults);
            foreach (self::DEFINITIONS as $key => [$type]) {
                $values[$key] = match ($type) {
                    'boolean' => filter_var($values[$key], FILTER_VALIDATE_BOOL),
                    'integer' => max(0, (int) $values[$key]),
                    default => (string) $values[$key],
                };
            }
            $this->values = $values;
        }

        return $this->values;
    }

    public function get(string $key): mixed
    {
        return $this->all()[$key] ?? self::DEFINITIONS[$key][1] ?? null;
    }

    public function int(string $key): int { return (int) $this->get($key); }
    public function bool(string $key): bool { return (bool) $this->get($key); }
    public function minor(string $key): int { return Money::toMinor((string) $this->get($key)); }

    public function forget(): void { $this->values = null; }
}
