<?php

namespace Database\Seeders;

use App\Models\CapabilityRegistry;
use Illuminate\Database\Seeder;

/**
 * Seeds the founding DSL 1.0 capabilities into the Capability Registry.
 */
class CapabilityRegistrySeeder extends Seeder
{
    public function run(): void
    {
        $capabilities = [
            [
                'name'              => 'crop_prices',
                'ops'               => ['read', 'aggregate'],
                'fields'            => [
                    'crop', 'date', 'market',
                    'modal_price', 'min_price', 'max_price', 'arrivals_qty',
                ],
                'introduced_in_dsl' => '1.0',
                'introduced_by'     => 'core',
                'status'            => 'active',
                'description'       => 'Daily crop market prices — APMC/mandi data.',
            ],
            [
                'name'              => 'weather_data',
                'ops'               => ['read'],
                'fields'            => [
                    'date', 'district', 'rainfall_mm',
                    'temp_max_c', 'temp_min_c', 'humidity_pct',
                ],
                'introduced_in_dsl' => '1.0',
                'introduced_by'     => 'core',
                'status'            => 'active',
                'description'       => 'Daily weather observations per district.',
            ],
            [
                'name'              => 'soil_health',
                'ops'               => ['read'],
                'fields'            => [
                    'date', 'farm_id', 'ph', 'nitrogen_ppm',
                    'phosphorus_ppm', 'potassium_ppm', 'organic_matter_pct',
                ],
                'introduced_in_dsl' => '1.0',
                'introduced_by'     => 'core',
                'status'            => 'active',
                'description'       => 'Soil health card readings per farm.',
            ],
        ];

        foreach ($capabilities as $data) {
            CapabilityRegistry::firstOrCreate(
                ['name' => $data['name']],
                $data,
            );
        }
    }
}
