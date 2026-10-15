<?php

namespace App\Services;

use App\Models\ResearchTemplateVersion;
use App\Models\ResearchType;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ResearchTemplateService
{
    public function createVersion(ResearchType $type, array $data, User $actor): ResearchTemplateVersion
    {
        return DB::transaction(function () use ($type, $data, $actor) {
            $next = ((int) $type->templateVersions()->max('version')) + 1;
            if ($data['is_active'] ?? true) {
                $type->templateVersions()->where('is_active', true)->update(['is_active' => false, 'retired_at' => now()]);
            }
            $schema = $this->normalizeSchema($data['template_schema'] ?? []);
            $version = $type->templateVersions()->create([
                'version' => $next,
                'name' => $data['name'] ?? $type->name.' Template v'.$next,
                'template_schema' => $schema,
                'validation_rules' => $data['validation_rules'] ?? $type->validation_rules,
                'citation_style' => strtolower($data['citation_style'] ?? 'apa'),
                'is_active' => $data['is_active'] ?? true,
                'effective_from' => $data['effective_from'] ?? now(),
                'created_by' => $actor->id,
            ]);
            if ($version->is_active) {
                $type->update(['template_schema' => $schema, 'validation_rules' => $version->validation_rules]);
            }
            return $version;
        });
    }

    public function activate(ResearchTemplateVersion $version): ResearchTemplateVersion
    {
        return DB::transaction(function () use ($version) {
            $version->researchType()->firstOrFail()->templateVersions()->where('is_active', true)->where('id', '!=', $version->id)->update(['is_active' => false, 'retired_at' => now()]);
            $version->update(['is_active' => true, 'effective_from' => $version->effective_from ?? now(), 'retired_at' => null]);
            $version->researchType()->update(['template_schema' => $version->template_schema, 'validation_rules' => $version->validation_rules]);
            return $version->fresh();
        });
    }

    public function retire(ResearchTemplateVersion $version): void
    {
        abort_if($version->researchType()->firstOrFail()->projects()->where('research_template_version_id', $version->id)->whereNotIn('status', ['approved', 'archived'])->exists(), 422, 'A template used by active projects cannot be retired until another active version is selected.');
        $version->update(['is_active' => false, 'retired_at' => now()]);
    }

    private function normalizeSchema(array|string $schema): array
    {
        if (is_string($schema)) {
            $decoded = json_decode($schema, true);
            $schema = is_array($decoded) ? $decoded : preg_split('/\r?\n/', $schema, -1, PREG_SPLIT_NO_EMPTY);
        }
        $sections = $schema['sections'] ?? $schema;
        $normalized = collect($sections)->values()->map(function ($section, $index) {
            if (is_string($section)) {
                return ['key' => Str::slug($section, '_'), 'title' => trim($section), 'required' => true, 'locked_after_approval' => true];
            }
            return [
                'key' => $section['key'] ?? Str::slug($section['title'] ?? 'section-'.($index + 1), '_'),
                'title' => $section['title'] ?? 'Section '.($index + 1),
                'required' => (bool) ($section['required'] ?? true),
                'initial_content' => $section['initial_content'] ?? '',
                'locked_after_approval' => (bool) ($section['locked_after_approval'] ?? true),
                'min_words' => (int) ($section['min_words'] ?? 0),
                'max_words' => isset($section['max_words']) ? (int) $section['max_words'] : null,
            ];
        })->all();
        return ['sections' => $normalized, 'metadata_fields' => $schema['metadata_fields'] ?? [], 'export_settings' => $schema['export_settings'] ?? []];
    }
}
