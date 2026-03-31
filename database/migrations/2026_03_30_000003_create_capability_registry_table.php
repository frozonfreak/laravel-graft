<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('capability_registry', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->unique();
            $table->json('ops');            // ["read","aggregate"] or ["shortcut"]
            $table->json('fields');         // allowed field names for this capability
            $table->string('introduced_in_dsl');   // e.g. "1.0"
            $table->string('introduced_by');        // 'core' | 'promotion:evol_id'
            $table->string('status')->default('active'); // active | deprecated
            $table->string('deprecated_in_dsl')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index('introduced_in_dsl');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('capability_registry');
    }
};
