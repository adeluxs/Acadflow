<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

/** Deterministic calculations on supplied observations; never synthesises participant data. */
class ResearchAnalysisService
{
    public function describe(string $csv): array
    {
        if (strlen($csv) > 2 * 1024 * 1024) $this->invalid('Use a CSV of at most 2 MB.');
        $stream = fopen('php://temp', 'r+');
        try {
            fwrite($stream, $csv); rewind($stream);
            $headers = fgetcsv($stream, 0, ',', '"', '');
            if (! $headers || count($headers) > 50) $this->invalid('Provide a CSV header with 1–50 columns.');
            $headers = array_map(fn ($h) => trim(ltrim((string) $h, "\xEF\xBB\xBF")), $headers);
            if (in_array('', $headers, true) || count(array_unique($headers)) !== count($headers)) $this->invalid('CSV headers must be non-empty and unique.');
            $columns = array_fill(0, count($headers), []);
            $rowCount = 0;
            while (($row = fgetcsv($stream, 0, ',', '"', '')) !== false) {
                if ($row === [null]) continue;
                if (++$rowCount > 5000) $this->invalid('Limit analysis to 5,000 rows per file.');
                if (count($row) !== count($headers)) $this->invalid('Every CSV row must have the same number of columns as the header.');
                foreach ($row as $i => $value) $columns[$i][] = trim((string) $value);
            }
            if ($rowCount === 0) $this->invalid('The CSV needs at least one observation.');
            $summary = [];
            foreach ($headers as $i => $header) {
                $values = array_values(array_filter($columns[$i], fn ($v) => $v !== ''));
                $numeric = $values !== [] && count(array_filter($values, fn ($v) => is_numeric($v) && is_finite((float) $v))) === count($values);
                $stats = ['column' => $header, 'observed' => count($values), 'missing' => $rowCount - count($values), 'type' => $numeric ? 'numeric' : 'categorical'];
                if ($numeric) {
                    $numbers = array_map('floatval', $values); sort($numbers); $n = count($numbers);
                    // Incremental mean/variance avoids overflow from sum(x*x).
                    $mean = 0.0; $m2 = 0.0;
                    foreach ($numbers as $index => $x) { $delta = $x - $mean; $mean += $delta / ($index + 1); $m2 += $delta * ($x - $mean); }
                    $stats += ['mean' => $mean, 'median' => $n % 2 ? $numbers[intdiv($n, 2)] : $numbers[$n / 2 - 1] / 2 + $numbers[$n / 2] / 2, 'sample_sd' => $n > 1 ? sqrt($m2 / ($n - 1)) : null, 'min' => min($numbers), 'max' => max($numbers)];
                    foreach ($stats as $v) if (is_float($v) && ! is_finite($v)) $this->invalid('Numeric values exceed the supported calculation range.');
                } else {
                    // Frequency counts deliberately omitted: participant labels and rare categories may identify people.
                    $stats['distinct'] = count(array_unique($values));
                }
                $summary[] = $stats;
            }
            return ['engine' => 'descriptive-v1', 'rows' => $rowCount, 'columns' => $summary, 'missing_rule' => 'Empty cells excluded per column. Other missing-value codes are treated as observed; recode them before upload.', 'interpretation' => 'Descriptive summaries only. No significance tests, causal conclusions or fabricated observations. Review data quality and disclosure risk before export.'];
        } finally { fclose($stream); }
    }

    public function sampleSize(float $confidence, float $margin, float $proportion, ?int $population, float $responseRate): array
    {
        $z = [90 => 1.644853627, 95 => 1.959963985, 99 => 2.575829304][(int) $confidence] ?? null;
        if (! $z || $margin <= 0 || $margin >= 1 || $proportion <= 0 || $proportion >= 1 || $responseRate <= 0 || $responseRate > 1 || ($population !== null && $population < 1)) $this->invalid('Provide valid precision, proportion, response rate and population.');
        $initial = $z * $z * $proportion * (1 - $proportion) / ($margin * $margin);
        $adjusted = $population ? $initial / (1 + ($initial - 1) / $population) : $initial;
        return ['engine' => 'single-proportion-v1', 'confidence_percent' => $confidence, 'margin' => $margin, 'proportion' => $proportion, 'population' => $population, 'response_rate' => $responseRate, 'completed_responses' => (int) ceil($adjusted), 'invite' => (int) ceil(ceil($adjusted) / $responseRate), 'assumptions' => 'Planning for one proportion under simple random sampling using a normal approximation, with optional finite-population correction. Not a power calculation, cluster design adjustment, qualitative sample rule or ethics approval. Seek statistical review for your design.'];
    }

    private function invalid(string $message): never { throw ValidationException::withMessages(['file' => $message]); }
}
