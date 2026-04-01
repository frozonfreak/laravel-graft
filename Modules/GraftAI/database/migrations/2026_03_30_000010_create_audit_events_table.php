<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->nullable();
            $table->uuid('feature_id')->nullable();
            $table->string('event_type', 100);
            $table->string('actor', 100);
            $table->json('detail')->nullable();
            $table->timestamp('created_at')->useCurrent();
            // No updated_at — immutable append-only log

            $table->index(['tenant_id', 'event_type']);
            $table->index(['feature_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_events');
    }
};
