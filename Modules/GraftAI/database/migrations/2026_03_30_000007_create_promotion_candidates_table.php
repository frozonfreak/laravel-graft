<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotion_candidates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('pipeline_signature', 64)->unique();
            $table->unsignedInteger('distinct_tenants');
            $table->unsignedInteger('distinct_features');
            $table->unsignedInteger('weighted_exec_score');
            $table->float('success_rate');
            $table->float('avg_feedback_score')->nullable();
            $table->string('risk_tier', 20);
            // low | medium | high | critical
            $table->string('status', 30)->default('pending');
            // pending | approved | rejected | promoted | promoted_then_reverted
            $table->string('reviewed_by', 100)->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('promoted_at')->nullable();
            $table->string('dsl_version_after', 10)->nullable();
            $table->timestamps();

            $table->index(['status', 'weighted_exec_score']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotion_candidates');
    }
};
