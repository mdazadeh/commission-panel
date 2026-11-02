<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\RuleVersion;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Persian;
use Illuminate\Support\Facades\DB;
use Morilog\Jalali\CalendarUtils;

/** One-time bootstrap used by the web installer and by tests. */
class InitialSetup
{
    public function __construct(private FiscalCalendar $calendar, private AuditLogger $audit) {}

    public function run(string $tenantName, string $adminUsername, string $adminName, string $adminPassword): User
    {
        return DB::transaction(function () use ($tenantName, $adminUsername, $adminName, $adminPassword) {
            $tenant = Tenant::firstOrCreate(['code' => 'faran'], ['name' => $tenantName]);

            $admin = User::create([
                'tenant_id' => $tenant->id,
                'username' => $adminUsername,
                'name' => $adminName,
                'password' => $adminPassword,
                'role' => Role::Admin,
            ]);

            [$jy] = CalendarUtils::toJalali((int) date('Y'), (int) date('n'), (int) date('j'));
            $this->calendar->ensureYear($tenant->id, $jy);

            RuleVersion::withoutGlobalScopes()->firstOrCreate(
                ['tenant_id' => $tenant->id, 'code' => '1405-v1'],
                [
                    'title' => 'قوانین پورسانت ۱۴۰۵ — پیش‌نویس براساس تصمیمات D-01 تا D-08',
                    'effective_from' => Persian::jalaliToDate('1405/01/01')->toDateString(),
                    'status' => 'draft',
                    'parameters' => json_decode(file_get_contents(database_path('data/rule-version-1405-v1.json')), true),
                ],
            );

            $this->audit->log('system.installed', $admin, null, ['tenant' => $tenant->name, 'admin' => $adminUsername], null, $tenant->id);

            return $admin;
        });
    }
}
