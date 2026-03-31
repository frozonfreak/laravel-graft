<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feature_executions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('feature_id');
            $table->uuid('tenant_id');
            $table->string('status', 20);   // running | success | failure | timeout
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('execution_ms')->nullable();
            $table->unsignedInteger('rows_scanned')->nullable();
            $table->float('cost_actual')->nullable();
            $table->json('error_detail')->nullable();
            $table->boolean('signal_emitted')->default(false);
            $table->timestamps();

            $table->foreign('feature_id')->references('id')->on('feature_configs')->cascadeOnDelete();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index(['feature_id', 'status']);
            $table->index(['tenant_id', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feature_executions');
    }
};
