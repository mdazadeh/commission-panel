<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\ImportBatch;
use App\Models\SalesDocument;
use App\Models\Team;
use App\Models\Tenant;
use App\Models\User;
use App\Services\FiscalCalendar;
use App\Support\Persian;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** AT-08: no user reaches data or functions beyond their role — via UI, URL or API. */
class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_expert_is_denied_admin_and_data_management_routes(): void
    {
        $expert = $this->makeUser(Role::SalesExpert);

        foreach (['/users', '/users/create', '/imports', '/audit', '/system'] as $uri) {
            $this->actingAs($expert)->get($uri)->assertForbidden();
        }
        $this->actingAs($expert)->post('/imports')->assertForbidden();
        $this->actingAs($expert)->post('/system/migrate')->assertForbidden();
    }

    public function test_admin_manages_users_but_has_no_automatic_access_to_financial_data(): void
    {
        $admin = User::where('username', 'admin')->first() ?? $this->bootstrapTenant();

        $this->actingAs($admin)->get('/users')->assertOk();
        $this->actingAs($admin)->get('/imports')->assertForbidden();
        $this->actingAs($admin)->get('/')->assertInertia(fn (Assert $p) => $p->where('scope', 'none')->where('totals', null));
    }

    public function test_finance_can_manage_imports(): void
    {
        $finance = $this->makeUser(Role::Finance);
        $this->actingAs($finance)->get('/imports')->assertOk();
    }

    public function test_dashboard_scopes_sales_rows_by_role(): void
    {
        $admin = $this->bootstrapTenant();
        $team = Team::create(['tenant_id' => $admin->tenant_id, 'name' => 'دیجیتال ۱', 'department' => 'digital']);
        $other = Team::create(['tenant_id' => $admin->tenant_id, 'name' => 'کلاسیک ۱', 'department' => 'classic']);
        $a = $this->makeUser(Role::SalesExpert, ['team_id' => $team->id, 'marketer_code' => '24']);
        $b = $this->makeUser(Role::SalesExpert, ['team_id' => $team->id, 'marketer_code' => '23']);
        $c = $this->makeUser(Role::SalesExpert, ['team_id' => $other->id, 'marketer_code' => '19']);
        $head = $this->makeUser(Role::SalesHead, ['team_id' => $team->id]);
        $vp = $this->makeUser(Role::SalesVp);

        $period = app(FiscalCalendar::class)->periodFor($admin->tenant_id, Persian::jalaliToDate('1405/02/10'));
        $batch = ImportBatch::create(['tenant_id' => $admin->tenant_id, 'type' => 'sales', 'original_filename' => 'x', 'stored_path' => 'x', 'file_hash' => str_repeat('a', 64), 'status' => 'committed']);
        foreach ([[$a, 100], [$b, 200], [$c, 400]] as $i => [$u, $amount]) {
            SalesDocument::create([
                'tenant_id' => $admin->tenant_id, 'import_batch_id' => $batch->id, 'doc_type' => 'sale', 'serial' => "S{$i}",
                'issued_on' => '2026-04-30', 'issued_on_jalali' => '1405/02/10', 'fiscal_period_id' => $period->id,
                'customer_code' => 'C1', 'marketer_code' => $u->marketer_code, 'user_id' => $u->id,
                'gross_amount' => $amount, 'net_amount' => $amount, 'row_hash' => str_repeat((string) $i, 64),
            ]);
        }

        $q = ['period' => $period->id];
        $this->actingAs($a)->get('/?'.http_build_query($q))->assertInertia(fn (Assert $p) => $p->where('scope', 'own')->where('totals.net', '100')->where('byPerson', []));
        $this->actingAs($head)->get('/?'.http_build_query($q))->assertInertia(fn (Assert $p) => $p->where('scope', 'team')->where('totals.net', '300')->has('byPerson', 2));
        $this->actingAs($vp)->get('/?'.http_build_query($q))->assertInertia(fn (Assert $p) => $p->where('scope', 'all')->where('totals.net', '700'));
    }

    public function test_user_from_another_tenant_is_not_editable(): void
    {
        $admin = $this->bootstrapTenant();
        $foreignTenant = Tenant::create(['code' => 'other', 'name' => 'دیگر']);
        $foreign = User::factory()->create(['tenant_id' => $foreignTenant->id]);

        $this->actingAs($admin)->get("/users/{$foreign->id}/edit")->assertNotFound();
        $this->actingAs($admin)->put("/users/{$foreign->id}", ['username' => 'x', 'name' => 'x', 'role' => 'admin'])->assertNotFound();
    }
}
