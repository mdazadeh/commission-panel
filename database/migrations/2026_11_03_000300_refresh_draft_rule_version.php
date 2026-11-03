<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Installs made before phase 2 hold the older 1405-v1 draft parameters.
 * Drafts are never used for payment, so refreshing them from the data file is safe;
 * approved versions are immutable and are left untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        $params = file_get_contents(database_path('data/rule-version-1405-v1.json'));
        DB::table('rule_versions')->where('code', '1405-v1')->where('status', 'draft')
            ->update(['parameters' => $params, 'updated_at' => now()]);
    }

    public function down(): void {}
};
