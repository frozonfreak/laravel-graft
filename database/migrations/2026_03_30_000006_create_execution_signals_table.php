<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('execution_signals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // Not a FK — signal layer is decoupled from sandbox (anonymized)
            $table->uuid('feature_id');
            $table->string('pipeline_signature', 64);
            $table->string('dsl_version', 10);
            $table->string('data_source', 100);
            $table->string('execution_outcome', 20); // success | failure | timeout
            $table->boolean('action_triggered')->nullable();
            $table->unsignedInteger('execution_ms')->nullable();
            $table->unsignedInteger('rows_scanned')->nullable();
            $table->string('user_feedback', 20)->nullable(); // useful | not_useful
            $table->timestamp('emitted_at')->useCurrent();

            $table->index('pipeline_signature');
            $table->index(['feature_id', 'emitted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('execution_signals');
    }
};
