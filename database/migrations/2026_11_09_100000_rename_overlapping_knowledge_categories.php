<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Several subject categories had the same names as publication types ("Study Guides" vs
 * "Study guide", "Exam Preparation" vs "Exam preparation"...), so creators thought they were
 * choosing the same thing twice. Categories are now named as topics; types stay formats.
 *
 * Only the display name changes: IDs and slugs stay the same, so links, filters, guides and
 * Writing Challenge tracks keep working. A category an admin has already renamed is left alone.
 */
return new class extends Migration
{
    public const RENAMES = [
        'Research Outputs' => 'Research & Publications',
        'Research Insights' => 'Research Explained',
        'Study Guides' => 'Courses & Study Skills',
        'Exam Preparation' => 'Exams & Assessments',
        'Tutorials' => 'Practical Skills',
        'Programming Tutorials' => 'Programming & Technology',
        'Career and SIWES' => 'Careers, Internships & SIWES',
        'Projects and Case Studies' => 'Final Year & Group Projects',
        'Digital Resources' => 'Tools & Learning Resources',
        'Campus and Department Guides' => 'Campus & Departments',
    ];

    public function up(): void
    {
        $this->rename(self::RENAMES);
    }

    public function down(): void
    {
        $this->rename(array_flip(self::RENAMES));
    }

    /** @param array<string,string> $map */
    private function rename(array $map): void
    {
        foreach ($map as $from => $to) {
            DB::table('knowledge_categories')->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower($from)])
                ->update(['name' => $to, 'updated_at' => now()]);
        }
    }
};
