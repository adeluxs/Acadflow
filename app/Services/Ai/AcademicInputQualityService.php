<?php

declare(strict_types=1);

namespace App\Services\Ai;

/**
 * Fast local quality gate shared by user-facing AcadFlow assistants.
 * Obvious noise is rejected before retrieval or a paid provider request.
 */
class AcademicInputQualityService
{
    /** @return array{accepted:bool,reason:?string,message:?string,normalized:string} */
    public function assess(string $input): array
    {
        $input = trim($input);
        $normalized = trim((string) preg_replace('/\s+/u', ' ', mb_strtolower($input)));
        $visible = (string) preg_replace('/\s+/u', '', $input);
        $visibleCount = max(1, mb_strlen($visible));
        $alphaNumeric = preg_match_all('/[\pL\pN]/u', $input) ?: 0;
        $noise = preg_match_all('/[^\pL\pN\s?.!,;:\'"\-()\/]/u', $input) ?: 0;

        if (mb_strlen($normalized) < 3 || $alphaNumeric < 2) {
            return $this->reject('too_short', 'Please enter a clear academic question or request.', $normalized);
        }

        if (($noise / $visibleCount) > 0.35) {
            return $this->reject('excessive_symbols', 'I could not understand that request. Please rephrase it using normal words.', $normalized);
        }

        if (preg_match('/(.)\1{5,}/u', $normalized) === 1 || preg_match('/(.{1,3})\1{3,}/u', $normalized) === 1) {
            return $this->reject('repetitive_input', 'I could not understand that request. Please rephrase it clearly.', $normalized);
        }

        if (preg_match('/\b(?:asdf|asdfgh|qwer|qwerty|zxcv|hjkl|dfgh|sdfgh|poiuy|lkjhg)[a-z0-9]*\b/i', $normalized) === 1) {
            return $this->reject('keyboard_smash', 'I could not understand that request. Please ask a meaningful academic question.', $normalized);
        }

        $tokens = preg_split('/[^\pL\pN]+/u', $normalized, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $suspicious = 0;
        foreach ($tokens as $token) {
            if ($this->looksLikeNoiseToken($token)) $suspicious++;
        }

        if ($tokens !== [] && ($suspicious / count($tokens)) >= 0.60) {
            return $this->reject('likely_gibberish', 'I could not understand that question. Please rephrase it and keep it related to the academic context on this page.', $normalized);
        }

        return ['accepted' => true, 'reason' => null, 'message' => null, 'normalized' => $normalized];
    }

    private function looksLikeNoiseToken(string $token): bool
    {
        $token = mb_strtolower($token);
        $length = mb_strlen($token);
        if ($length < 6 || preg_match('/^[a-z]+$/i', $token) !== 1) return false;

        // Long alphabetic tokens without a normal vowel are usually keyboard
        // noise. Keep short acronyms and legitimate codes out of this heuristic.
        $hasVowel = preg_match('/[aeiou]/i', $token) === 1;
        $uniqueRatio = count(array_unique(str_split($token))) / max(1, strlen($token));
        $alternatingNoise = preg_match('/(?:[bcdfghjklmnpqrstvwxyz]{5,})/i', $token) === 1;

        return (! $hasVowel && $length >= 7) || ($alternatingNoise && $uniqueRatio < 0.65);
    }

    /** @return array{accepted:bool,reason:string,message:string,normalized:string} */
    private function reject(string $reason, string $message, string $normalized): array
    {
        return ['accepted' => false, 'reason' => $reason, 'message' => $message, 'normalized' => $normalized];
    }
}
