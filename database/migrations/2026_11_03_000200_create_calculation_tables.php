<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Every run is an immutable snapshot: inputs hash + rule version + results + per-document trace.
        Schema::create('calculation_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fiscal_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rule_version_id')->constrained()->restrictOnDelete();
            $table->string('status', 16)->default('provisional'); // provisional | final
            $table->char('inputs_hash', 64);
            $table->json('summary')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['tenant_id', 'fiscal_period_id']);
        });

        Schema::create('calculation_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('calculation_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 32);
            $table->decimal('sales_total', 20, 0);
            $table->decimal('target_total', 20, 0);
            $table->decimal('achievement', 12, 6);
            $table->decimal('tier_rate', 8, 5);
            $table->decimal('final_amount', 24, 4);
            $table->decimal('payable_amount', 20, 0);
            $table->json('components');
            $table->json('flags');
            $table->timestamps();
            $table->unique(['calculation_run_id', 'user_id']);
        });

        Schema::create('calculation_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('calculation_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sales_document_id')->constrained()->restrictOnDelete();
            $table->string('category', 24); // action_plan | rep_contract | rep_no_contract
            $table->decimal('net_amount', 20, 0);
            $table->decimal('cash_amount', 20, 0);
            $table->decimal('credit_amount', 20, 0);
            $table->decimal('rate', 8, 5);
            $table->decimal('commission', 24, 4);
            $table->string('note')->nullable();
            $table->index(['calculation_run_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calculation_lines');
        Schema::dropIfExists('calculation_results');
        Schema::dropIfExists('calculation_runs');
    }
};
