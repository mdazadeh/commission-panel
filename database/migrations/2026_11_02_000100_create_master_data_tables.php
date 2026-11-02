<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fiscal_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('jalali_year');
            $table->unsignedTinyInteger('quarter'); // 1=بهار … 4=زمستان
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('status', 16)->default('open'); // open | locked | settled
            $table->timestamp('locked_at')->nullable();
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['tenant_id', 'jalali_year', 'quarter']);
        });

        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('code', 32); // Farapayam «کد خریدار»
            $table->string('name');
            $table->string('category', 32)->nullable(); // rep_contract | rep_no_contract | end_user | intermediary
            $table->timestamps();
            $table->unique(['tenant_id', 'code']);
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('code', 64); // Farapayam «کد کالا»
            $table->string('name');
            $table->string('brand')->nullable();
            $table->boolean('is_faran_brand')->default(true);
            $table->string('kind', 16)->nullable(); // ups | battery | other
            $table->decimal('kva', 10, 3)->nullable();
            $table->decimal('ah', 10, 3)->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
        Schema::dropIfExists('customers');
        Schema::dropIfExists('fiscal_periods');
    }
};
