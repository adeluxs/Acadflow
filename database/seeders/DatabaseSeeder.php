<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            UniversitySeeder::class,
            NigeriaAcademicCatalogSeeder::class,
            AcadFlowEcosystemSeeder::class,
            SettingsSeeder::class,
            FeatureFlagSeeder::class,
        ]);
    }
}
