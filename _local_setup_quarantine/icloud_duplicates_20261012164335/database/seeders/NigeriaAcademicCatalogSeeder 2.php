<?php

namespace Database\Seeders;

use App\Services\NigeriaAcademicCatalogService;
use Illuminate\Database\Seeder;
use Throwable;

class NigeriaAcademicCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $service = app(NigeriaAcademicCatalogService::class);

        if ((bool) config('academic_catalog.remote_sync', true)) {
            try {
                $result = $service->syncInstitutions(allowFallback: true, preserveExisting: true);
                $this->command?->info(sprintf(
                    'Nigeria institution registry checked without overwriting existing rows: %d NUC university rows, %d NBTE rows, %d fallback polytechnic rows.',
                    $result['nuc'], $result['nbte'], $result['fallback_polytechnics']
                ));
                foreach ($result['warnings'] as $warning) $this->command?->warn($warning);
            } catch (Throwable $e) {
                $this->command?->warn('Remote Nigerian institution sync skipped: '.$e->getMessage());
            }
        }

        if ((bool) config('academic_catalog.seed_templates', true)) {
            $stats = $service->seedStarterTemplatesForAll();
            $this->command?->info(sprintf(
                'Starter academic catalogues ensured for %d institutions (%d new faculties, %d new departments, %d new courses).',
                $stats['institutions'], $stats['faculties'], $stats['departments'], $stats['courses']
            ));
        }
    }
}
