<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Fingerprints of shipped default instructions, not administrator content.
    private const LEGACY_PROMPT_HASHES = [
        '28921378ac904514f8ec10ece922fcc72b59ab064949b799454e69e5489e086a',
        'ac8b5ae12bf64b909340cc4713cef5b08c175e394984ce5623a2a4af0f93115b',
        '8eb28e063e3c5ad1233213bf63389179625d10c5fcb6d948a7ea107cd4621a53',
        '8eb123e287a90c4c9979c4cecee94247cb5a2909561aab248c85c5854c357d73',
        '7cae75fbb3df205780e1b3b70c3352a3b369ffcfa933858c9efe2f48cbc8842b',
        'a135750b87ec2556265a22a4953c660ea4831bb4dbda8139048df0b0101fe028',
        'acf2418d06798aec79b8a5598a6dbe78c0e2851f517f3d798744cc4c2bdfb26d',
        '62d4d694f4c62c0262bbe3e81ed1e8be4295b7c153df49b494f5f13db718b2d1',
        '031e3d811848ffdab9c59f2c089fb18fcac024c827ab1f63b8a3f3a0b7744099',
        'ef64641c6c0d0ec5215c75ba6ae6d469134f8231e2222f5a7583b8b8a2aa5f60',
        '4b735e73be350e6ed795c136f12d0f4137d5c5883ead95a3fd8610750f3b4dae',
        '3b2fb3dafaca1ddd74ccec21eb564b051b365163f897a930af37594663dab6f6',
        '2ba30cc88ef032a15f76b40721204a8fa4f30f85c7b37d81e0366b3029edeae2',
        '81caec476bc46c7187c60e9b8493c30694972ee71ce8d6be9a35b6f8ce38210b',
        '7bd0a2546a84f00d98522ccec40a1cf8be9b72d8baa40ebf550de006024e74e5',
        '2f99743a6480945baa8f1bdd809581201dcbfe510a85219df2f7dae6ce72ec61',
        '6294d12d7808f96398d2cc8bb7202d1c4081f2fa5bb7c4c6dff61bcfa2b926cc',
        '96974d4a07c37ed20fb7051b06eedc20335a8f0976ab65a3fbec5f81d690f583',
        '1390002e65a57348bcc853a0ec9f37718f41aa5e53821159bee84acae414a806',
        'c5258659e64ff6c08e3d2b5babd5db1021028072ba3b2be6fde9fcb936481a69',
        'd0cbb005c0318da547126f86f292bfea8028379a8f7536cd80af6a8167aa2d40',
        'b71fb52b92322f5afbebb81e432560eb027ae9bcc586bf531881958e70a89c0e',
        '2b7f7fe764d6a808a4822d9628311d0ff1194907f90d8fa11e8fc4acde3dc7eb',
        'd370f68a7a4d0149ad808dd350c7e684f8ffee8a6277ad3bffa0bdcc2a2d5591',
        '79cbc66b75785d0b9f501214a4bd702d6338f2e5558f8e8c30423a4b7851e3d3',
        'f5a9ced8cc6c8473c04aa2cbcb1d2c0bc4fce569e5b31b04248bd2ea5e48232d',
        '45056010ab9dc8c7b8d8bb91999287163f02623742e5d340d20fabde455ff32b',
        'f35bec64f7f8561a97f9ad0eafacc3e44e4e7626844cebbb5e616d1071678b40',
        'b3c030efc2dd580cd0bdc60ace13812db5f0ba5af9990e9930bafc779842b075',
        '00e73a08d99126276686ac4c9ef1fb23d537b6d2714508996101f89d8f2769f2',
        '9020bdb4bf774bfcc8c7b3cb118af2fe9703597c7e4c8c0da030529efd2d1cd1',
    ];

    public function up(): void
    {
        if (Schema::hasTable('settings')) {
            $scopes = [null];
            if (Schema::hasTable('universities')) $scopes = array_merge($scopes, DB::table('universities')->pluck('id')->all());
            foreach (['site_name', 'site_logo', 'site_favicon', 'logo_url', 'favicon_url', 'ai_global_system_prompt', 'support_email'] as $key) {
                $setting = DB::table('settings')->where('key', $key)->first();
                if (! $setting) continue;
                $next = $this->value($key, (string) $setting->value);
                if ($next !== $setting->value) DB::table('settings')->where('id', $setting->id)->update(['value' => $next]);
                if (Schema::hasTable('setting_overrides')) {
                    foreach (DB::table('setting_overrides')->where('setting_id', $setting->id)->get() as $override) {
                        $next = $this->value($key, (string) $override->value);
                        if ($next !== $override->value) DB::table('setting_overrides')->where('id', $override->id)->update(['value' => $next]);
                        $scopes[] = (int) $override->university_id;
                    }
                }
            }
            foreach (array_unique($scopes, SORT_REGULAR) as $scope) {
                foreach (['site_name', 'site_logo', 'site_favicon', 'logo_url', 'favicon_url', 'ai_global_system_prompt', 'support_email'] as $key) {
                    Cache::forget('setting.'.($scope ?: 'global').'.'.$key);
                }
                Cache::forget('settings.all.'.($scope ?: 'global'));
            }
        }

        if (Schema::hasTable('universities')) {
            DB::table('universities')->where('code', 'ACADFIND')->where('name', 'AcadFlow Independent Workspace')
                ->update(['name' => 'AcadSyntra Independent Workspace']);
        }

        if (Schema::hasTable('research_types')) {
            DB::table('research_types')->where('slug', 'independent-research')
                ->where('description', 'Flexible personal or collaborative research workspace for independent AcadFlow users.')
                ->update(['description' => 'Flexible personal or collaborative research workspace for independent AcadSyntra users.']);
        }

        if (Schema::hasTable('feature_flags')) {
            DB::table('feature_flags')->where('description', 'AcadFlow feature managed by centralized Feature & Module Management.')
                ->update(['description' => 'AcadSyntra feature managed by centralized Feature & Module Management.']);
        }

        if (Schema::hasTable('ai_prompt_versions')) {
            // Preserve the original version's instruction text and audit history.
            $rows = DB::table('ai_prompt_versions')->whereNull('university_id')->whereNull('created_by')->where('is_active', true)->get();
            foreach ($rows as $row) {
                if (! in_array(hash('sha256', (string) $row->system_prompt), self::LEGACY_PROMPT_HASHES, true)) continue;
                $next = $this->rename((string) $row->system_prompt);
                if ($next === $row->system_prompt) continue;
                DB::transaction(function () use ($row, $next): void {
                    $current = DB::table('ai_prompt_versions')->where('id', $row->id)->lockForUpdate()->first();
                    if (! $current || ! $current->is_active) return;
                    $copy = (array) $current;
                    unset($copy['id']);
                    $copy['version'] = 1 + (int) DB::table('ai_prompt_versions')->where('feature', $row->feature)->whereNull('university_id')->max('version');
                    $copy['system_prompt'] = $next;
                    if (is_string($copy['user_template'] ?? null)) $copy['user_template'] = $this->rename($copy['user_template']);
                    $copy['created_at'] = now();
                    $copy['updated_at'] = now();
                    DB::table('ai_prompt_versions')->insert($copy);
                    DB::table('ai_prompt_versions')->where('id', $row->id)->update(['is_active' => false]);
                }, 3);
            }
        }
        \App\Services\SettingService::flushRequestCache();
    }

    private function value(string $key, string $value): string
    {
        if ($key === 'support_email' && in_array(strtolower(trim($value)), ['support@uniflow.edu', 'support@example.com'], true)) return '';
        if ($key === 'site_name' && in_array(strtolower(trim($value)), ['acadflow', 'uniflow'], true)) return 'AcadSyntra';
        if (in_array($key, ['site_logo', 'site_favicon', 'logo_url', 'favicon_url'], true)
            && preg_match('#^/?branding/acadflow-(mark|logo|icon-(32|180|192|512))\.png$#', $value)) {
            return str_replace('branding/acadflow-', 'branding/acadsyntra-', $value);
        }
        if ($key === 'ai_global_system_prompt' && in_array(hash('sha256', $value), self::LEGACY_PROMPT_HASHES, true)) return $this->rename($value);
        return $value;
    }

    private function rename(string $value): string
    {
        return str_replace('AcadFlow', 'AcadSyntra', $value);
    }

    public function down(): void
    {
        // Branding rollback never deletes new enquiries, identities or prompt history.
    }
};
