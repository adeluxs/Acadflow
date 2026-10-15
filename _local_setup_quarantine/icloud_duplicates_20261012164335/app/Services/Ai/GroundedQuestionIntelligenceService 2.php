<?php

namespace App\Services\Ai;

use App\Models\AiGroundingSession;
use App\Models\KnowledgePublication;
use App\Models\User;
use App\Services\Discovery\LocalEmbeddingService;
use App\Services\SettingService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Local intelligence gate for the Knowledge Hub Grounded AI Companion.
 *
 * This service intentionally runs before any external provider call. It rejects
 * obvious keyboard-smash / meaningless input, classifies academic intent,
 * estimates publication relevance, builds retrieval expansions, and learns an
 * adaptive pattern profile from previously successful grounded sessions.
 *
 * Pattern learning is deliberately conservative: only successful, grounded,
 * non-negative sessions are learned from. Rejected/gibberish questions are
 * never promoted into the learned profile.
 */
class GroundedQuestionIntelligenceService
{
    private const GENERIC_PUBLICATION_INTENTS = [
        'summary', 'methodology', 'findings', 'limitations', 'conclusion', 'evidence', 'citation', 'explanation',
    ];

    private const STOP_WORDS = [
        'a','am','an','and','are','as','at','be','been','being','but','by','can','could','did','do','does','for','from','had','has','have','he','her','hers','him','his','how','i','if','in','into','is','it','its','may','me','more','most','my','of','on','or','our','ours','please','should','so','some','than','that','the','their','theirs','them','then','there','these','they','this','those','to','us','was','we','were','what','when','where','which','who','why','will','with','would','you','your','yours',
        'summarize','summary','overview','explain','clarify','simplify','define','definition','meaning','compare','comparison','contrast','method','methods','methodology','finding','findings','result','results','outcome','outcomes','limitation','limitations','conclusion','conclusions','recommendation','recommendations','evidence','proof','citation','citations','reference','references','source','sources','article','document','paper','publication','resource','study','work','author','authors','say','says','tell','show','shows','about','main','key','major','overall','used','use','using','reach','reaches','identify','identifies','identified','provide','provides','provided','give','gives','given','no','ok','up','go',
    ];

    private const INTENT_TERMS = [
        'summary' => ['summarize','summary','overview','main idea','main point','main argument','what is this about','what is this publication about','what is this paper about','what is this article about','what is the publication about','tell me about this publication','tell me about this paper','key points'],
        'methodology' => ['method','methods','methodology','research design','sample','sampling','procedure','procedures','data collection','instrument','analysis method'],
        'findings' => ['finding','findings','result','results','outcome','outcomes','discovered','observed'],
        'limitations' => ['limitation','limitations','weakness','weaknesses','constraint','constraints','drawback','drawbacks'],
        'conclusion' => ['conclusion','conclusions','conclude','concludes','recommendation','recommendations','implication','implications'],
        'evidence' => ['evidence','support','supports','proof','data','example','examples','basis','justify','justifies'],
        'citation' => ['cite','citation','reference','references','source','sources','where does it say','which section'],
        'comparison' => ['compare','comparison','contrast','difference','differences','similarity','similarities','versus',' vs '],
        'definition' => ['define','definition','meaning','what is','what does .* mean'],
        'explanation' => ['explain','clarify','simplify','simple terms','understand','why does','how does','interpret'],
    ];

    private const INTENT_EXPANSIONS = [
        'summary' => 'overview main argument key points purpose scope',
        'methodology' => 'method methodology research design sample procedure data collection analysis',
        'findings' => 'findings results outcomes observations evidence',
        'limitations' => 'limitations weaknesses constraints drawbacks caveats',
        'conclusion' => 'conclusion recommendations implications final findings',
        'evidence' => 'evidence data support examples findings basis',
        'citation' => 'reference citation source section evidence',
        'comparison' => 'compare contrast differences similarities',
        'definition' => 'definition meaning concept describes',
        'explanation' => 'explain interpretation meaning mechanism reason',
    ];

    public function __construct(private readonly LocalEmbeddingService $embeddings) {}

    /** @return array<string,mixed> */
    public function assess(KnowledgePublication $publication, string $question, User $user): array
    {
        $publication->loadMissing('document', 'tags', 'category');

        $question = trim($question);
        $normalized = $this->normalize($question);
        $publicationTerms = $this->publicationTerms($publication);
        $terms = $this->meaningfulTerms($normalized);
        $intent = $this->classifyIntent($normalized);
        $genericIntent = $this->isGenericPublicationIntent($normalized, $terms, $intent);
        $profile = $this->patternProfile($publication, $user);

        $reasons = [];
        $minChars = max(2, (int) SettingService::get('ai_grounded_min_question_chars', 3, $user->university_id));
        $alphaCount = preg_match_all('/\pL/u', $question) ?: 0;
        $visibleCount = max(1, mb_strlen(preg_replace('/\s+/u', '', $question) ?? $question));
        $noiseCount = preg_match_all('/[^\pL\pN\s?.!,;:\'"\-()\/]/u', $question) ?: 0;
        $noiseRatio = $noiseCount / $visibleCount;

        if (mb_strlen($normalized) < $minChars) {
            $reasons[] = 'too_short';
        }
        if ($alphaCount < 2 && ! preg_match('/\d/u', $question)) {
            $reasons[] = 'no_meaningful_language';
        }
        if ($noiseRatio > 0.35) {
            $reasons[] = 'excessive_symbols';
        }
        if (preg_match('/(.)\1{5,}/u', $normalized) === 1 || preg_match('/(.{1,3})\1{3,}/u', $normalized) === 1) {
            $reasons[] = 'repetitive_input';
        }
        if (preg_match('/\b(?:asdf|asdfgh|qwer|qwerty|zxcv|hjkl|dfgh|sdfgh|poiuy|lkjhg)[a-z]*\b/i', $normalized) === 1) {
            $reasons[] = 'keyboard_smash';
        }

        $wordTokens = $this->wordTokens($normalized);
        $gibberishCount = 0;
        foreach ($wordTokens as $token) {
            if ($this->isLikelyGibberishToken($token, $publicationTerms)) {
                $gibberishCount++;
            }
        }
        $gibberishRatio = $wordTokens === [] ? 1.0 : ($gibberishCount / count($wordTokens));
        $gibberishThreshold = min(1.0, max(0.2, (float) SettingService::get('ai_grounded_gibberish_threshold', 0.60, $user->university_id)));
        if (($wordTokens !== [] && $gibberishRatio >= $gibberishThreshold)
            || (count($wordTokens) === 1 && $gibberishCount === 1)) {
            $reasons[] = 'likely_gibberish';
        }

        $publicationOverlap = $this->termOverlap($terms, array_keys($publicationTerms));
        $semantic = max(0.0, $this->embeddings->cosine(
            $this->embeddings->embed($normalized),
            $this->embeddings->embed($this->publicationSignalText($publication))
        ));

        $learnedTerms = array_keys((array) ($profile['terms'] ?? []));
        $patternTermOverlap = $this->termOverlap($terms, $learnedTerms);
        $intentSeen = (int) data_get($profile, 'intents.'.$intent, 0) > 0;
        $patternScore = min(1.0, ($patternTermOverlap * 0.75) + ($intentSeen ? 0.25 : 0.0));

        $preliminaryRelevance = min(1.0, max(0.0,
            ($publicationOverlap * 0.58)
            + ($semantic * 0.27)
            + ($genericIntent ? 0.10 : 0.0)
            + ($patternScore * 0.05)
        ));

        $accepted = $reasons === [];

        return [
            'accepted' => $accepted,
            'normalized_question' => $normalized,
            'intent' => $intent,
            'generic_publication_intent' => $genericIntent,
            'terms' => array_values($terms),
            'reasons' => array_values(array_unique($reasons)),
            'gibberish_score' => round($gibberishRatio, 4),
            'noise_ratio' => round($noiseRatio, 4),
            'publication_term_overlap' => round($publicationOverlap, 4),
            'semantic_hint' => round($semantic, 4),
            'pattern_score' => round($patternScore, 4),
            'learned_from_sessions' => (int) ($profile['successful_sessions'] ?? 0),
            'preliminary_relevance' => round($preliminaryRelevance, 4),
            'retrieval_query' => $this->retrievalQuery($publication, $normalized, $intent, $genericIntent),
            'suggestions' => $this->suggestions($publication, $intent, $profile),
        ];
    }

    /**
     * Applies the second-stage evidence gate after publication-scoped retrieval.
     *
     * @param Collection<int,array<string,mixed>> $chunks
     * @return array<string,mixed>
     */
    public function assessEvidence(array $assessment, Collection $chunks, User $user): array
    {
        if ($chunks->isEmpty()) {
            return [
                'accepted' => false,
                'reason' => 'publication_not_indexed',
                'score' => 0.0,
                'top_score' => 0.0,
                'top_lexical_score' => 0.0,
                'top_semantic_score' => 0.0,
            ];
        }

        $topScore = (float) $chunks->max(fn (array $item) => (float) ($item['score'] ?? 0));
        $topLexical = (float) $chunks->max(fn (array $item) => (float) ($item['lexical_score'] ?? 0));
        $topSemantic = (float) $chunks->max(fn (array $item) => (float) ($item['semantic_score'] ?? 0));
        $threshold = min(0.95, max(0.05, (float) SettingService::get('ai_grounded_relevance_threshold', 0.18, $user->university_id)));
        $lexicalFloor = min(0.95, max(0.0, (float) SettingService::get('ai_grounded_lexical_floor', 0.20, $user->university_id)));

        $generic = (bool) ($assessment['generic_publication_intent'] ?? false);
        $topicTerms = array_values((array) ($assessment['terms'] ?? []));
        $topicCount = count($topicTerms);
        $topicOverlap = (float) ($assessment['publication_term_overlap'] ?? 0.0);

        if ($generic) {
            $accepted = true;
        } elseif ($topicCount === 0) {
            $accepted = false;
        } else {
            // Specific questions must have real topical evidence. One incidental
            // word match must not make an unrelated question answerable. For one
            // or two topic terms we require strong coverage; longer questions may
            // pass with a majority-like match or a genuinely strong semantic hit.
            $requiredCoverage = $topicCount <= 2 ? 0.60 : 0.34;
            $lexicallySupported = $topLexical >= max($lexicalFloor, $requiredCoverage);
            $strongSemanticSupport = $topScore >= $threshold
                && $topSemantic >= 0.30
                && $topicOverlap >= 0.20;
            $accepted = $lexicallySupported || $strongSemanticSupport;
        }

        return [
            'accepted' => $accepted,
            'reason' => $accepted ? null : 'question_not_supported_by_publication',
            'score' => round(min(1.0, max(0.0, ($topScore * 0.75) + ($topLexical * 0.15) + ($topSemantic * 0.10))), 4),
            'top_score' => round($topScore, 4),
            'top_lexical_score' => round($topLexical, 4),
            'top_semantic_score' => round($topSemantic, 4),
            'threshold' => round($threshold, 4),
            'topic_term_count' => $topicCount,
            'topic_overlap' => round($topicOverlap, 4),
        ];
    }

    public function clearPatternCache(KnowledgePublication $publication): void
    {
        Cache::forget($this->patternCacheKey($publication));
    }

    /**
     * Update the cached pattern profile immediately after a successful grounded
     * answer. This avoids re-querying the sessions table on every request while
     * still letting the companion adapt to useful question patterns quickly.
     * Negative feedback later clears the cache and rebuilds the profile from
     * trusted session history, so bad patterns do not become permanent.
     *
     * @param array<string,mixed> $assessment
     */
    public function learnFromSuccessfulSession(KnowledgePublication $publication, array $assessment, User $user): void
    {
        if (! (bool) SettingService::get('ai_grounded_pattern_learning_enabled', true, $user->university_id)) {
            return;
        }

        $key = $this->patternCacheKey($publication);
        $profile = Cache::get($key);
        if (! is_array($profile)) {
            // No warm profile exists yet. Let the next request build it from the
            // persisted sessions instead of doing another database query here.
            return;
        }

        $profile['successful_sessions'] = (int) ($profile['successful_sessions'] ?? 0) + 1;
        $intent = (string) ($assessment['intent'] ?? 'question');
        $profile['intents'][$intent] = (int) data_get($profile, 'intents.'.$intent, 0) + 1;

        foreach ((array) ($assessment['terms'] ?? []) as $term) {
            $term = $this->normalize((string) $term);
            if ($term === '' || mb_strlen($term) < 2) continue;
            $profile['terms'][$term] = (int) data_get($profile, 'terms.'.$term, 0) + 1;
        }

        arsort($profile['terms']);
        arsort($profile['intents']);
        $profile['terms'] = array_slice($profile['terms'], 0, 80, true);

        Cache::put($key, $profile, now()->addMinutes(10));
    }

    /** @return array<string,mixed> */
    private function patternProfile(KnowledgePublication $publication, User $user): array
    {
        if (! (bool) SettingService::get('ai_grounded_pattern_learning_enabled', true, $user->university_id)) {
            return ['successful_sessions' => 0, 'terms' => [], 'intents' => []];
        }

        return Cache::remember($this->patternCacheKey($publication), now()->addMinutes(10), function () use ($publication): array {
            $sessions = AiGroundingSession::query()
                ->where('feature', 'knowledge_companion')
                ->where('subject_type', $publication->getMorphClass())
                ->where('subject_id', $publication->getKey())
                ->where('status', 'completed')
                ->latest('id')
                ->limit(120)
                ->get(['metadata', 'confidence']);

            $termCounts = [];
            $intentCounts = [];
            $successful = 0;

            foreach ($sessions as $session) {
                $meta = is_array($session->metadata) ? $session->metadata : [];
                $intelligence = (array) data_get($meta, 'question_intelligence', []);
                $feedback = (string) data_get($meta, 'feedback.rating', '');
                $accepted = (bool) ($intelligence['accepted'] ?? false);
                $evidenceAccepted = (bool) data_get($meta, 'evidence_gate.accepted', false);
                $confidence = (float) ($session->confidence ?? 0);

                if (! $accepted || ! $evidenceAccepted || $feedback === 'not_helpful' || $confidence < 35) {
                    continue;
                }

                $weight = $feedback === 'helpful' ? 2 : 1;
                $successful += $weight;
                $intent = (string) ($intelligence['intent'] ?? 'question');
                $intentCounts[$intent] = ($intentCounts[$intent] ?? 0) + $weight;

                foreach ((array) ($intelligence['terms'] ?? []) as $term) {
                    $term = $this->normalize((string) $term);
                    if ($term === '' || mb_strlen($term) < 2) continue;
                    $termCounts[$term] = ($termCounts[$term] ?? 0) + $weight;
                }
            }

            arsort($termCounts);
            arsort($intentCounts);

            return [
                'successful_sessions' => $successful,
                'terms' => array_slice($termCounts, 0, 80, true),
                'intents' => $intentCounts,
            ];
        });
    }

    private function patternCacheKey(KnowledgePublication $publication): string
    {
        return 'grounded:pattern:'.hash('sha256', $publication->getMorphClass().':'.$publication->getKey());
    }

    private function retrievalQuery(KnowledgePublication $publication, string $question, string $intent, bool $genericIntent): string
    {
        $expansion = self::INTENT_EXPANSIONS[$intent] ?? '';

        if ($genericIntent) {
            return trim($question.' '.$expansion.' '.$publication->title);
        }

        // For a specific topical question, do not add generic intent vocabulary.
        // Otherwise unrelated questions could gain false relevance merely because
        // words such as "definition" or "evidence" occur in the publication.
        return trim($question);
    }

    /** @return list<string> */
    private function suggestions(KnowledgePublication $publication, string $intent, array $profile = []): array
    {
        $title = Str::limit((string) $publication->title, 90, '…');
        $byIntent = [
            'summary' => 'Summarize the main argument and key points of “'.$title.'”.',
            'methodology' => 'Explain the methodology or research design used in this publication.',
            'findings' => 'What are the main findings or results reported in this publication?',
            'limitations' => 'What limitations, implications, or recommendations does this publication identify?',
            'conclusion' => 'What conclusion does this publication reach, and what evidence supports it?',
            'evidence' => 'What evidence or findings in this publication support its main conclusion?',
            'citation' => 'Which section of this publication supports the main conclusion?',
            'explanation' => 'Explain the publication’s main idea in simpler academic language.',
        ];

        $base = [];
        if (isset($byIntent[$intent])) {
            $base[] = $byIntent[$intent];
        }

        // Adaptive pattern learning affects suggestions, not truth. Frequently
        // successful/helpful intents for this publication are promoted so the
        // companion becomes more useful over time without ever bypassing the
        // publication-evidence gate.
        foreach (array_keys((array) ($profile['intents'] ?? [])) as $learnedIntent) {
            if (isset($byIntent[$learnedIntent])) {
                $base[] = $byIntent[$learnedIntent];
            }
        }

        $base = array_merge($base, [
            $byIntent['summary'],
            $byIntent['evidence'],
            $byIntent['limitations'],
        ]);

        return array_values(array_unique(array_slice($base, 0, 4)));
    }

    private function classifyIntent(string $normalized): string
    {
        foreach (self::INTENT_TERMS as $intent => $patterns) {
            foreach ($patterns as $pattern) {
                if (str_contains($pattern, '.*')) {
                    if (preg_match('/\b'.str_replace('\.\*', '.*', preg_quote($pattern, '/')).'\b/iu', $normalized) === 1) {
                        return $intent;
                    }
                } elseif (str_contains($normalized, $pattern)) {
                    return $intent;
                }
            }
        }

        return 'question';
    }

    private function isGenericPublicationIntent(string $normalized, array $terms, string $intent): bool
    {
        if (! in_array($intent, self::GENERIC_PUBLICATION_INTENTS, true)) {
            return false;
        }

        // A generic publication request is one whose meaningful terms are intent
        // words/qualifiers only. A real topic term (for example "photosynthesis"
        // or "AI") keeps the request specific so it must pass relevance gates.
        $intentWords = $this->meaningfulTerms(self::INTENT_EXPANSIONS[$intent] ?? $intent);
        $qualifiers = ['main','key','major','overall','used','use','using','reach','reaches','identify','identifies','identified','provide','provides','provided','give','gives','given'];
        $specificTerms = array_values(array_diff($terms, $intentWords, $qualifiers));

        return $specificTerms === []
            || preg_match('/\b(this|the)\s+(publication|paper|article|study|document|resource)\b/iu', $normalized) === 1;
    }

    /** @return array<string,int> */
    private function publicationTerms(KnowledgePublication $publication): array
    {
        $text = $this->publicationSignalText($publication).' '.($publication->document?->body ?? '');
        $tokens = $this->meaningfulTerms($this->normalize(strip_tags((string) $text)));
        $counts = array_count_values($tokens);
        arsort($counts);

        return array_slice($counts, 0, 500, true);
    }

    private function publicationSignalText(KnowledgePublication $publication): string
    {
        return trim(implode(' ', array_filter([
            (string) $publication->title,
            (string) $publication->excerpt,
            (string) $publication->category?->name,
            $publication->tags?->pluck('name')->implode(' ') ?? '',
        ])));
    }

    /** @return list<string> */
    private function meaningfulTerms(string $text): array
    {
        return array_values(array_unique(array_filter($this->wordTokens($text), function (string $word): bool {
            return mb_strlen($word) >= 2 && ! in_array($word, self::STOP_WORDS, true);
        })));
    }

    /** @return list<string> */
    private function wordTokens(string $text): array
    {
        return preg_split('/[^\pL\pN]+/u', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    private function isLikelyGibberishToken(string $token, array $publicationTerms): bool
    {
        if ((int) ($publicationTerms[$token] ?? 0) >= 1) {
            return false;
        }

        $length = mb_strlen($token);
        if ($length < 5 || preg_match('/\d/u', $token)) {
            return false;
        }

        $lettersOnly = preg_replace('/[^\pL]/u', '', $token) ?? $token;
        $letterLength = max(1, mb_strlen($lettersOnly));
        $vowels = preg_match_all('/[aeiouy]/iu', $lettersOnly) ?: 0;
        $vowelRatio = $vowels / $letterLength;
        preg_match_all('/./u', $lettersOnly, $chars);
        $uniqueRatio = count(array_unique($chars[0] ?? [])) / $letterLength;
        $consonantRun = preg_match('/[^aeiouy\W\d_]{6,}/iu', $lettersOnly) === 1;

        if ($letterLength >= 7 && $vowelRatio < 0.08) return true;
        if ($letterLength >= 9 && $vowelRatio > 0.82) return true;
        if ($letterLength >= 9 && $uniqueRatio < 0.34) return true;
        if ($letterLength >= 8 && $consonantRun) return true;

        return false;
    }

    private function termOverlap(array $left, array $right): float
    {
        if ($left === [] || $right === []) return 0.0;
        $rightMap = array_fill_keys($right, true);
        $matches = 0;
        foreach ($left as $term) {
            if (isset($rightMap[$term]) || $this->hasCloseTermMatch((string) $term, $right)) {
                $matches++;
            }
        }

        return min(1.0, $matches / max(1, count($left)));
    }

    /**
     * Conservative typo tolerance for meaningful academic terms. This lets a
     * question such as "photosynthsis" still match "photosynthesis" while
     * keeping short/random tokens out of the fuzzy path.
     */
    private function hasCloseTermMatch(string $term, array $candidates): bool
    {
        $term = mb_strtolower($term);
        $length = mb_strlen($term);
        if ($length < 5 || preg_match('/^[a-z0-9]+$/i', $term) !== 1) {
            return false;
        }

        $maxDistance = $length >= 8 ? 2 : 1;
        $first = mb_substr($term, 0, 1);
        foreach ($candidates as $candidate) {
            $candidate = mb_strtolower((string) $candidate);
            if ($candidate === '' || mb_substr($candidate, 0, 1) !== $first) continue;
            if (abs(mb_strlen($candidate) - $length) > $maxDistance) continue;
            if (preg_match('/^[a-z0-9]+$/i', $candidate) !== 1) continue;
            if (levenshtein($term, $candidate) <= $maxDistance) return true;
        }

        return false;
    }

    private function normalize(string $value): string
    {
        $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value);

        return mb_strtolower($value);
    }
}
