<?php

namespace GraftAI\Database\Seeders;

use Illuminate\Database\Seeder;

class GraftAIDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call([
            CapabilityRegistrySeeder::class,
            DemoSeeder::class,
        ]);
    }
}
