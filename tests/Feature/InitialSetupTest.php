<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\FiscalPeriod;
use App\Models\RuleVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InitialSetupTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_tenant_admin_periods_and_draft_rules(): void
    {
        $admin = $this->bootstrapTenant();

        $this->assertSame(Role::Admin, $admin->role);
        $this->assertSame(4, FiscalPeriod::withoutGlobalScopes()->count());

        $rules = RuleVersion::withoutGlobalScopes()->where('code', '1405-v1')->first();
        $this->assertSame('draft', $rules->status);
        $this->assertSame('0.50', $rules->parameters['minimum_achievement_gate']['above']);
        $this->assertSame('0.15', $rules->parameters['deductions']['high_discount']['avg_discount_above']);
        $this->assertSame('0.35', $rules->parameters['deductions']['non_cash']['rate']);
    }
}
