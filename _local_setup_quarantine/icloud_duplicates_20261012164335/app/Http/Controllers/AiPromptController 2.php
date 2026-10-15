<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Models\AiPromptVersion;
use App\Services\Ai\AiPromptService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AiPromptController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $this->authorizeManage($request);
        $data = $request->validate([
            'feature' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9_\-]+$/'],
            'system_prompt' => ['required', 'string', 'max:50000'],
            'user_template' => ['required', 'string', 'max:50000'],
            'response_schema' => ['nullable'],
            'settings' => ['nullable'],
            'activate' => ['nullable', 'boolean'],
        ]);
        $universityId = $request->user()->isSuperAdmin() && $request->boolean('global_scope') ? null : $request->user()->university_id;
        $schema = $this->jsonObject($data['response_schema'] ?? '{}', 'response_schema');
        $settings = $this->jsonObject($data['settings'] ?? '{}', 'settings');

        DB::transaction(function () use ($request, $data, $universityId, $schema, $settings): void {
            $version = ((int) AiPromptVersion::where('university_id', $universityId)->where('feature', $data['feature'])->max('version')) + 1;
            if ($request->boolean('activate')) AiPromptVersion::where('university_id', $universityId)->where('feature', $data['feature'])->update(['is_active' => false]);
            AiPromptVersion::create([
                'university_id' => $universityId, 'feature' => $data['feature'], 'version' => $version,
                'system_prompt' => $data['system_prompt'], 'user_template' => $data['user_template'],
                'response_schema' => $schema, 'settings' => $settings,
                'is_active' => $request->boolean('activate'), 'created_by' => $request->user()->id,
            ]);
        });

        if ($request->boolean('activate')) {
            app(\App\Ai\AiManager::class)->invalidateFeature($data['feature']);
        }

        return back()->with('success', 'An immutable AI prompt version was created.');
    }

    public function activate(Request $request, AiPromptVersion $prompt): RedirectResponse
    {
        $this->authorizeManage($request);
        abort_unless($request->user()->isSuperAdmin() || $prompt->university_id === null || $prompt->university_id === $request->user()->university_id, 403);
        DB::transaction(function () use ($prompt): void {
            AiPromptVersion::where('university_id', $prompt->university_id)->where('feature', $prompt->feature)->update(['is_active' => false]);
            $prompt->update(['is_active' => true]);
        });
        app(AiPromptService::class); // Resolve service now so deployment container failures surface immediately.
        app(\App\Ai\AiManager::class)->invalidateFeature($prompt->feature);
        return back()->with('success', 'Prompt version activated and cached responses invalidated.');
    }

    private function authorizeManage(Request $request): void
    {
        abort_unless($request->user()?->hasPermission(Permission::MANAGE_AI_SETTINGS), 403);
    }

    private function jsonObject(mixed $value, string $field): array
    {
        if (is_array($value)) $decoded = $value;
        else {
            try { $decoded = json_decode((string) $value, true, flags: JSON_THROW_ON_ERROR); }
            catch (\JsonException $e) { throw ValidationException::withMessages([$field => 'Invalid JSON: '.$e->getMessage()]); }
        }
        if (! is_array($decoded) || array_is_list($decoded)) throw ValidationException::withMessages([$field => 'A JSON object is required.']);
        return $decoded;
    }
}
