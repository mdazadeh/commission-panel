<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Quarterly target and per-period financial inputs per person (rial, integer).
        Schema::create('targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fiscal_period_id')->constrained()->cascadeOnDelete();
            $table->decimal('annual_target', 20, 0)->default(0);
            $table->decimal('quarter_target', 20, 0)->default(0);
            // null = derived automatically from the previous quarter's result (TG-03).
            $table->decimal('carried_over_override', 20, 0)->nullable();
            $table->decimal('lost_sales', 20, 0)->default(0);        // «فروش از دست رفته کالای فاران» (AC-02)
            $table->decimal('prior_receivables', 20, 0)->default(0); // «مطالبات معوق فصول قبل» (DD-03)
            $table->string('notes')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'fiscal_period_id']);
        });

        Schema::create('action_plan_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('jalali_year');
            $table->unsignedTinyInteger('jalali_month');
            $table->string('customer_code', 32);
            $table->string('customer_name');
            $table->string('lead_status', 16); // cold | warm | hot | current
            $table->string('segment')->nullable();
            $table->string('product_forecast')->nullable();
            $table->decimal('amount_forecast', 20, 0)->nullable();
            $table->string('notes', 500)->nullable();
            $table->string('status', 16)->default('submitted'); // submitted | approved | rejected
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('review_note')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'jalali_year', 'jalali_month', 'customer_code'], 'action_plan_unique_item');
            $table->index(['tenant_id', 'jalali_year', 'jalali_month', 'customer_code'], 'action_plan_lookup');
        });

        Schema::create('activity_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('jalali_year');
            $table->unsignedTinyInteger('jalali_month');
            $table->string('indicator', 32); // customer_meetings | action_plan | internal_meetings
            $table->unsignedInteger('target');
            $table->unsignedInteger('actual');
            $table->string('evidence', 1000)->nullable();
            $table->string('status', 16)->default('pending'); // pending | approved | rejected
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('review_note')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'jalali_year', 'jalali_month', 'indicator'], 'activity_unique_entry');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_entries');
        Schema::dropIfExists('action_plan_items');
        Schema::dropIfExists('targets');
    }
};
