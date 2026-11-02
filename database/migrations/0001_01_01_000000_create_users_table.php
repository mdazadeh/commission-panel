<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name');
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('department', 32); // digital | classic | online
            $table->unsignedBigInteger('head_user_id')->nullable()->index();
            $table->timestamps();
            $table->unique(['tenant_id', 'name']);
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('username', 64)->unique();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('password');
            $table->string('role', 32)->index();
            $table->foreignId('team_id')->nullable()->constrained()->nullOnDelete();
            // Farapayam «کد بازاریاب» — the only key used to attribute sales to a person.
            $table->string('marketer_code', 32)->nullable();
            $table->date('sales_started_on')->nullable(); // seniority → annual target band
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->unique(['tenant_id', 'marketer_code']);
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('users');
        Schema::dropIfExists('teams');
        Schema::dropIfExists('tenants');
    }
};
