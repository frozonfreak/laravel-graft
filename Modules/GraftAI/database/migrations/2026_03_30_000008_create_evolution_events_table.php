<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evolution_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type', 50);
            // operator_promoted | capability_added | capability_deprecated
            // dsl_version_bumped | system_rollback
            $table->string('operator_name', 100)->nullable();
            $table->string('promoted_from_signature', 64)->nullable();
            $table->string('dsl_version_before', 10);
            $table->string('dsl_version_after', 10);
            $table->unsignedInteger('contributing_tenant_count')->nullable();
            $table->string('promoted_by', 100);
            $table->text('notes')->nullable();
            $table->timestamp('promoted_at')->useCurrent();
            // Immutable — no updated_at
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evolution_events');
    }
};
