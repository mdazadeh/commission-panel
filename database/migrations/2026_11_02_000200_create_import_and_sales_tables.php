<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('type', 32); // sales | action_plan | activity | targets
            $table->string('original_filename');
            $table->string('stored_path');
            $table->char('file_hash', 64);
            $table->string('status', 16); // validated | committed | rejected
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('valid_rows')->default(0);
            $table->unsignedInteger('error_rows')->default(0);
            $table->unsignedInteger('duplicate_rows')->default(0);
            $table->unsignedInteger('inserted_rows')->default(0);
            $table->json('meta')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('committed_at')->nullable();
            $table->timestamps();
            // AT-01: the same file can never be imported twice.
            $table->unique(['tenant_id', 'type', 'file_hash']);
        });

        Schema::create('import_issues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_batch_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->string('severity', 8); // error | warning
            $table->string('code', 48);
            $table->string('message');
            $table->json('raw')->nullable();
            $table->index(['import_batch_id', 'severity']);
        });

        Schema::create('sales_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('import_batch_id')->constrained()->restrictOnDelete();
            $table->string('doc_type', 16); // sale | return | non_operational
            $table->string('serial', 32);
            $table->unsignedInteger('line_no')->default(1);
            $table->date('issued_on');
            $table->string('issued_on_jalali', 10);
            $table->foreignId('fiscal_period_id')->nullable()->constrained()->nullOnDelete();
            $table->string('period_override_reason')->nullable();
            $table->string('customer_code', 32);
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('marketer_code', 32)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('department', 32)->nullable();
            $table->string('product_code', 64)->nullable();
            $table->decimal('quantity', 14, 3)->nullable();
            // Amounts in RIAL, integer — Farapayam float noise is rounded at import.
            $table->decimal('gross_amount', 20, 0);
            $table->decimal('discount_amount', 20, 0)->default(0);
            $table->decimal('net_amount', 20, 0);
            $table->decimal('cash_amount', 20, 0)->default(0);
            $table->decimal('credit_amount', 20, 0)->default(0); // «تعهدی» → non-cash (D-04)
            $table->char('row_hash', 64);
            $table->timestamps();
            // Farapayam reuses serials (sale vs return, and returns on different dates) → type + serial + date + line.
            $table->unique(['tenant_id', 'doc_type', 'serial', 'issued_on', 'line_no'], 'sales_doc_natural_key');
            $table->index(['tenant_id', 'fiscal_period_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_documents');
        Schema::dropIfExists('import_issues');
        Schema::dropIfExists('import_batches');
    }
};
