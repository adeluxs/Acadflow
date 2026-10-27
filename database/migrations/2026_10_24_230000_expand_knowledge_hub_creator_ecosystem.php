<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('knowledge_publication_types')) {
            Schema::create('knowledge_publication_types', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('slug', 100)->unique();
                $table->string('area', 50)->index();
                $table->text('description')->nullable();
                $table->string('icon', 50)->nullable();
                $table->json('field_schema')->nullable();
                $table->boolean('supports_series')->default(false);
                $table->boolean('is_academic')->default(false);
                $table->boolean('is_active')->default(true);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();
                $table->index(['is_active', 'sort_order']);
            });
        }

        Schema::table('knowledge_categories', function (Blueprint $table): void {
            if (! Schema::hasColumn('knowledge_categories', 'area')) {
                $table->string('area', 50)->default('academics_research')->after('description')->index();
            }
            if (! Schema::hasColumn('knowledge_categories', 'icon')) {
                $table->string('icon', 50)->nullable()->after('area');
            }
            if (! Schema::hasColumn('knowledge_categories', 'allowed_publication_types')) {
                $table->json('allowed_publication_types')->nullable()->after('icon');
            }
            if (! Schema::hasColumn('knowledge_categories', 'sort_order')) {
                $table->unsignedSmallInteger('sort_order')->default(0)->after('allowed_publication_types');
            }
        });

        if (! Schema::hasTable('knowledge_series')) {
            Schema::create('knowledge_series', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('creator_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('category_id')->nullable()->constrained('knowledge_categories')->nullOnDelete();
                $table->foreignId('publication_type_id')->nullable()->constrained('knowledge_publication_types')->nullOnDelete();
                $table->string('title');
                $table->string('slug')->unique();
                $table->text('description')->nullable();
                $table->string('genre', 100)->nullable();
                $table->string('visibility', 30)->default('public');
                $table->string('status', 30)->default('draft');
                $table->string('completion_status', 30)->default('ongoing');
                $table->string('cover_path')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('published_at')->nullable();
                $table->timestamps();
                $table->softDeletes();
                $table->index(['status', 'visibility', 'published_at']);
                $table->index(['creator_id', 'status']);
            });
        }

        Schema::table('knowledge_publications', function (Blueprint $table): void {
            if (! Schema::hasColumn('knowledge_publications', 'publication_type_id')) {
                $table->foreignId('publication_type_id')->nullable()->after('category_id')->constrained('knowledge_publication_types')->nullOnDelete();
            }
            if (! Schema::hasColumn('knowledge_publications', 'series_id')) {
                $table->foreignId('series_id')->nullable()->after('publication_type_id')->constrained('knowledge_series')->nullOnDelete();
            }
            if (! Schema::hasColumn('knowledge_publications', 'chapter_number')) {
                $table->unsignedInteger('chapter_number')->nullable()->after('series_id');
            }
            if (! Schema::hasColumn('knowledge_publications', 'audience')) {
                $table->string('audience', 80)->nullable()->after('language');
            }
            if (! Schema::hasColumn('knowledge_publications', 'content_warning')) {
                $table->text('content_warning')->nullable()->after('excerpt');
            }
            if (! Schema::hasColumn('knowledge_publications', 'ownership_attested_at')) {
                $table->timestamp('ownership_attested_at')->nullable()->after('content_warning');
            }
            if (! Schema::hasColumn('knowledge_publications', 'license_type')) {
                $table->string('license_type', 80)->nullable()->after('ownership_attested_at');
            }
        });

        if (! Schema::hasTable('knowledge_reading_progress')) {
            Schema::create('knowledge_reading_progress', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('knowledge_series_id')->constrained('knowledge_series')->cascadeOnDelete();
                $table->foreignId('knowledge_publication_id')->nullable()->constrained('knowledge_publications')->nullOnDelete();
                $table->unsignedTinyInteger('progress_percent')->default(0);
                $table->timestamp('last_read_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
                $table->unique(['user_id', 'knowledge_series_id'], 'knowledge_reading_progress_user_series_uq');
                $table->index(['user_id', 'last_read_at']);
            });
        }

        if (! Schema::hasTable('creator_monetization_applications')) {
            Schema::create('creator_monetization_applications', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('status', 30)->default('pending');
                $table->boolean('ownership_declaration')->default(false);
                $table->boolean('terms_accepted')->default(false);
                $table->text('statement')->nullable();
                $table->foreignId('payout_account_id')->nullable()->constrained('payout_accounts')->nullOnDelete();
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->text('review_note')->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamp('suspended_at')->nullable();
                $table->timestamps();
                $table->index(['status', 'submitted_at']);
                $table->index(['user_id', 'status']);
            });
        }

        $types = [
            ['Research paper', 'research_paper', 'academics_research', true, false],
            ['Journal article', 'journal_article', 'academics_research', true, false],
            ['Preprint', 'preprint', 'academics_research', true, false],
            ['Thesis or dissertation', 'thesis_dissertation', 'academics_research', true, false],
            ['Lecture notes', 'lecture_notes', 'academics_research', true, false],
            ['Academic resource', 'academic_resource', 'academics_research', true, false],
            ['Campus experience', 'campus_experience', 'campus_student_life', false, false],
            ['SIWES or internship story', 'siwes_experience', 'campus_student_life', false, false],
            ['Personal story', 'personal_story', 'life_personal_growth', false, false],
            ['Fiction', 'fiction', 'stories_creativity', false, true],
            ['Short story', 'short_story', 'stories_creativity', false, true],
            ['Poem', 'poem', 'stories_creativity', false, false],
            ['Creative nonfiction', 'creative_nonfiction', 'stories_creativity', false, true],
            ['Tutorial', 'tutorial', 'skills_business_professional', false, false],
            ['Case study', 'case_study', 'skills_business_professional', false, false],
            ['Professional guide', 'professional_guide', 'skills_business_professional', false, false],
            ['Report', 'report', 'skills_business_professional', false, false],
            ['Downloadable resource', 'downloadable_resource', 'skills_business_professional', false, false],
            ['Academic article (legacy)', 'academic_article', 'academics_research', true, false],
            ['Research output (legacy)', 'research_output', 'academics_research', true, false],
            ['Research insight', 'research_insight', 'academics_research', true, false],
            ['Study guide', 'study_guide', 'academics_research', true, false],
            ['Exam preparation', 'exam_preparation', 'academics_research', true, false],
            ['Programming tutorial', 'programming_tutorial', 'skills_business_professional', false, false],
            ['Campus guide', 'campus_guide', 'campus_student_life', false, false],
            ['Career guide', 'career_guide', 'skills_business_professional', false, false],
            ['Project experience', 'project_experience', 'campus_student_life', false, false],
            ['Educational video', 'educational_video', 'skills_business_professional', false, false],
            ['Presentation', 'presentation', 'academics_research', false, false],
            ['Reference material', 'reference_material', 'academics_research', true, false],
            ['Announcement', 'announcement', 'campus_student_life', false, false],
            ['Digital resource (legacy)', 'digital_resource', 'skills_business_professional', false, false],
            ['Question bank', 'question_bank', 'academics_research', true, false],
            ['Mock examination', 'mock_exam', 'academics_research', true, false],
            ['Template', 'template', 'skills_business_professional', false, false],
            ['E-book', 'ebook', 'skills_business_professional', false, false],
            ['Video course', 'video_course', 'skills_business_professional', false, false],
            ['Article', 'article', 'skills_business_professional', false, false],
        ];
        foreach ($types as $index => [$name, $slug, $area, $academic, $series]) {
            DB::table('knowledge_publication_types')->updateOrInsert(['slug' => $slug], [
                'name' => $name, 'area' => $area, 'is_academic' => $academic, 'supports_series' => $series,
                'is_active' => true, 'sort_order' => $index + 1, 'updated_at' => now(), 'created_at' => now(),
            ]);
        }
        DB::table('knowledge_publications')->whereNull('publication_type_id')->orderBy('id')->eachById(function ($publication): void {
            $typeId = DB::table('knowledge_publication_types')->where('slug', $publication->content_type)->value('id');
            if ($typeId) {
                DB::table('knowledge_publications')->where('id', $publication->id)->update(['publication_type_id' => $typeId]);
            }
        });

        $areas = [
            ['Academics & Research', 'academics-research', 'academics_research', 'book-open'],
            ['Campus & Student Life', 'campus-student-life', 'campus_student_life', 'academic-cap'],
            ['Stories & Creativity', 'stories-creativity', 'stories_creativity', 'sparkles'],
            ['Life & Personal Growth', 'life-personal-growth', 'life_personal_growth', 'heart'],
            ['Skills, Business & Careers', 'skills-business-careers', 'skills_business_professional', 'briefcase'],
        ];
        foreach ($areas as $index => [$name, $slug, $area, $icon]) {
            $existing = DB::table('knowledge_categories')->whereNull('university_id')->where('slug', $slug)->first();
            if ($existing) {
                DB::table('knowledge_categories')->where('id', $existing->id)->update(['area' => $area, 'icon' => $icon, 'sort_order' => ($index + 1) * 10, 'is_active' => true, 'updated_at' => now()]);
            } else {
                DB::table('knowledge_categories')->insert(['university_id' => null, 'parent_id' => null, 'name' => $name, 'slug' => $slug, 'description' => 'Explore '.Str::lower($name).' on AcadSyntra.', 'area' => $area, 'icon' => $icon, 'sort_order' => ($index + 1) * 10, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('creator_monetization_applications');
        Schema::dropIfExists('knowledge_reading_progress');
        Schema::table('knowledge_publications', function (Blueprint $table): void {
            foreach (['publication_type_id', 'series_id'] as $column) {
                if (Schema::hasColumn('knowledge_publications', $column)) {
                    $table->dropConstrainedForeignId($column);
                }
            }
            foreach (['chapter_number', 'audience', 'content_warning', 'ownership_attested_at', 'license_type'] as $column) {
                if (Schema::hasColumn('knowledge_publications', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
        Schema::dropIfExists('knowledge_series');
        Schema::table('knowledge_categories', function (Blueprint $table): void {
            if (Schema::hasColumn('knowledge_categories', 'area')) {
                $table->dropIndex(['area']);
            }
        });
        Schema::table('knowledge_categories', function (Blueprint $table): void {
            foreach (['area', 'icon', 'allowed_publication_types', 'sort_order'] as $column) {
                if (Schema::hasColumn('knowledge_categories', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
        Schema::dropIfExists('knowledge_publication_types');
    }
};
