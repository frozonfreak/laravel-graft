<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feature_snapshots', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->nullable();
            $table->string('snapshot_type', 20);  // tenant | system
            $table->string('label', 255)->nullable();
            $table->json('features');
            $table->string('dsl_version_at_snapshot', 10);
            $table->string('created_by', 100);
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('tenant_id')->references('id')->on('tenants')->nullOnDelete();
            $table->index(['tenant_id', 'snapshot_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feature_snapshots');
    }
};
