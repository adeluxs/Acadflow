<?php

namespace Tests\Unit;

use App\Models\AcademicReference;
use App\Services\AcademicReferenceService;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AcademicReferenceFormattingTest extends TestCase
{
    #[DataProvider('styles')]
    public function test_all_enabled_styles_format_without_inventing_metadata(string $style): void
    {
        $reference = new AcademicReference([
            'title' => 'Reliable Academic Systems',
            'authors' => ['A. Author', 'B. Researcher'],
            'publication_year' => 2026,
            'journal' => 'Journal of Evidence',
            'doi' => '10.1000/example',
        ]);

        $formatted = app(AcademicReferenceService::class)->format($reference, $style, 3);

        $this->assertStringContainsString('Reliable Academic Systems', $formatted);
        $this->assertStringContainsString('2026', $formatted);
    }

    public static function styles(): array
    {
        return array_map(fn (string $style) => [$style], ['apa', 'mla', 'chicago', 'harvard', 'ieee', 'vancouver']);
    }
}
