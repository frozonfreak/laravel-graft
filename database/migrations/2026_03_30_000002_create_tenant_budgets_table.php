<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_budgets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->integer('billing_month'); // YYYYMM e.g. 202603
            $table->unsignedInteger('monthly_limit')->default(5000);
            $table->unsignedInteger('consumed')->default(0);
            $table->string('status')->default('active'); // active | warning | halted
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique(['tenant_id', 'billing_month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_budgets');
    }
};
