<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feature_configs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('dsl_version', 10)->default('1.0');
            $table->unsignedInteger('feature_version')->default(1);
            $table->string('lifecycle_stage', 20)->default('sandbox');
            // sandbox | candidate | promoted | deprecated
            $table->string('type', 50);        // alert | report | etc.
            $table->string('data_source', 100);
            $table->json('pipeline');
            $table->json('action');
            $table->json('schedule')->nullable();
            $table->string('status', 20)->default('active');
            // active | suspended | degraded | archived | pending_approval | promoted
            $table->unsignedSmallInteger('trust_tier');
            $table->json('cost_estimate');
            $table->string('pipeline_signature', 64);  // sha256 of canonical shape
            $table->boolean('contributes_to_evolution')->default(false);
            $table->boolean('promoted_to_core')->default(false);
            $table->string('created_by', 10);  // ai | user | system
            $table->timestamp('last_executed_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index(['tenant_id', 'status']);
            $table->index('pipeline_signature');
            $table->index('lifecycle_stage');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feature_configs');
    }
};
