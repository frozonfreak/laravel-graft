<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('status')->default('active'); // active | suspended | in_arrears
            $table->json('evolution_settings')->default(json_encode([
                'contribute_to_pattern_detection'   => false,
                'receive_promoted_feature_notifications' => true,
                'auto_migrate_promoted_configs'     => false,
            ]));
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
