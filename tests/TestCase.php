<?php

namespace Tests;

use App\Enums\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\InitialSetup;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function bootstrapTenant(): User
    {
        return app(InitialSetup::class)->run('فاران', 'admin', 'مدیر', 'admin-pass-1234');
    }

    protected function makeUser(Role $role, array $attrs = []): User
    {
        $tenantId = Tenant::query()->value('id') ?? $this->bootstrapTenant()->tenant_id;

        return User::factory()->role($role)->create(['tenant_id' => $tenantId] + $attrs);
    }
}
