<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\ActionPlanItem;
use App\Models\ActivityEntry;
use App\Models\AuditEvent;
use App\Models\CalculationResult;
use App\Models\Customer;
use App\Models\FiscalPeriod;
use App\Models\ImportBatch;
use App\Models\SalesDocument;
use App\Models\Target;
use App\Models\Team;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** End-to-end HTTP flow of phase 2: inputs → review (four eyes) → calculation → scoped results. */
class Phase2FlowTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $expert;

    private User $other;

    private User $head;

    private User $finance;

    private FiscalPeriod $q1;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->bootstrapTenant();
        $team = Team::create(['tenant_id' => $this->admin->tenant_id, 'name' => 'دیجیتال', 'department' => 'digital']);
        $otherTeam = Team::create(['tenant_id' => $this->admin->tenant_id, 'name' => 'کلاسیک', 'department' => 'classic']);
        $this->expert = $this->makeUser(Role::SalesExpert, ['marketer_code' => '24', 'team_id' => $team->id]);
        $this->other = $this->makeUser(Role::SalesExpert, ['marketer_code' => '19', 'team_id' => $otherTeam->id]);
        $this->head = $this->makeUser(Role::SalesHead, ['team_id' => $team->id]);
        $this->finance = $this->makeUser(Role::Finance);
        $this->q1 = FiscalPeriod::withoutGlobalScopes()->where(['jalali_year' => 1405, 'quarter' => 1])->firstOrFail();
    }

    public function test_targets_are_managed_by_finance_only_and_audited(): void
    {
        $this->actingAs($this->expert)->get('/targets')->assertForbidden();
        $this->actingAs($this->finance)->get("/targets?period={$this->q1->id}")->assertOk()
            ->assertInertia(fn (Assert $p) => $p->where('quarterShare', '0.15')->has('rows', 3));

        $this->actingAs($this->finance)->put("/targets/{$this->q1->id}/{$this->expert->id}", [
            'annual_target' => '600000000000', 'quarter_target' => '90000000000', 'lost_sales' => '', 'prior_receivables' => '1,000',
        ])->assertSessionHasErrors('prior_receivables');

        $this->actingAs($this->finance)->put("/targets/{$this->q1->id}/{$this->expert->id}", [
            'annual_target' => '600000000000', 'quarter_target' => '90000000000',
        ])->assertSessionHasNoErrors();
        $this->assertSame('90000000000', (string) Target::where('user_id', $this->expert->id)->value('quarter_target'));
        $this->assertTrue(AuditEvent::where('action', 'target.saved')->exists());
    }

    public function test_action_plan_four_eyes_review(): void
    {
        $this->actingAs($this->expert)->post('/action-plans', ['month' => '1405-01', 'customer_code' => '0401000002', 'customer_name' => 'بانک', 'lead_status' => 'warm'])
            ->assertSessionHasNoErrors();
        $item = ActionPlanItem::firstOrFail();
        $this->assertSame('submitted', $item->status);

        // duplicate in same month is refused
        $this->actingAs($this->expert)->post('/action-plans', ['month' => '1405-01', 'customer_code' => '0401000002', 'customer_name' => 'بانک', 'lead_status' => 'warm'])
            ->assertSessionHasErrors('customer_code');

        // the owner, an expert of another team and a head of another team cannot review
        $this->actingAs($this->expert)->post("/action-plans/{$item->id}/review", ['decision' => 'approved'])->assertForbidden();
        $otherHead = $this->makeUser(Role::SalesHead, ['team_id' => $this->other->team_id]);
        $this->actingAs($otherHead)->post("/action-plans/{$item->id}/review", ['decision' => 'approved'])->assertForbidden();

        // reject requires a reason
        $this->actingAs($this->head)->post("/action-plans/{$item->id}/review", ['decision' => 'rejected'])->assertSessionHasErrors('note');
        $this->actingAs($this->head)->post("/action-plans/{$item->id}/review", ['decision' => 'approved'])->assertSessionHasNoErrors();
        $this->assertSame('approved', $item->fresh()->status);
        $this->assertSame($this->head->id, $item->fresh()->reviewed_by);

        // approved rows cannot be deleted
        $this->actingAs($this->expert)->delete("/action-plans/{$item->id}")->assertSessionHas('error');

        // the head's own plan must be reviewed by someone else
        $this->actingAs($this->head)->post('/action-plans', ['month' => '1405-01', 'customer_code' => 'H1', 'customer_name' => 'x', 'lead_status' => 'hot']);
        $own = ActionPlanItem::where('user_id', $this->head->id)->firstOrFail();
        $this->actingAs($this->head)->post("/action-plans/{$own->id}/review", ['decision' => 'approved'])->assertForbidden();
        $this->assertTrue(app(AuditLogger::class)->verify()['ok']);
    }

    public function test_activity_submission_and_approval(): void
    {
        $this->actingAs($this->expert)->get('/activity?month=1405-01')->assertOk()
            ->assertInertia(fn (Assert $p) => $p->has('own', 3)->where('own.0.target', 12));

        $this->actingAs($this->expert)->post('/activity', ['month' => '1405-01', 'indicator' => 'customer_meetings', 'target' => 12, 'actual' => 9])->assertSessionHasNoErrors();
        $entry = ActivityEntry::firstOrFail();
        $this->actingAs($this->head)->post("/activity/{$entry->id}/review", ['decision' => 'approved'])->assertSessionHasNoErrors();

        // approved entry is frozen for the owner
        $this->actingAs($this->expert)->post('/activity', ['month' => '1405-01', 'indicator' => 'customer_meetings', 'target' => 12, 'actual' => 12])->assertSessionHas('error');
        $this->assertSame(9, $entry->fresh()->actual);
    }

    public function test_calculation_run_and_result_visibility(): void
    {
        Target::create(['tenant_id' => $this->expert->tenant_id, 'user_id' => $this->expert->id, 'fiscal_period_id' => $this->q1->id, 'quarter_target' => '1000000000']);
        Target::create(['tenant_id' => $this->expert->tenant_id, 'user_id' => $this->other->id, 'fiscal_period_id' => $this->q1->id, 'quarter_target' => '1000000000']);
        Customer::create(['tenant_id' => $this->expert->tenant_id, 'code' => 'C1', 'name' => 'x']);
        $batch = ImportBatch::create(['tenant_id' => $this->expert->tenant_id, 'type' => 'sales', 'original_filename' => 'x', 'stored_path' => 'x', 'file_hash' => str_repeat('c', 64), 'status' => 'committed']);
        SalesDocument::create(['tenant_id' => $this->expert->tenant_id, 'import_batch_id' => $batch->id, 'doc_type' => 'sale', 'serial' => 'S1', 'issued_on' => '2026-04-01',
            'issued_on_jalali' => '1405/01/12', 'fiscal_period_id' => $this->q1->id, 'customer_code' => 'C1', 'marketer_code' => '24', 'user_id' => $this->expert->id,
            'gross_amount' => 900000000, 'net_amount' => 900000000, 'cash_amount' => 900000000, 'credit_amount' => 0, 'row_hash' => str_repeat('d', 64)]);

        $this->actingAs($this->expert)->post('/calculations', ['period' => $this->q1->id])->assertForbidden();
        $this->actingAs($this->finance)->post('/calculations', ['period' => $this->q1->id])->assertRedirect();

        $mine = CalculationResult::where('user_id', $this->expert->id)->firstOrFail();
        $theirs = CalculationResult::where('user_id', $this->other->id)->firstOrFail();

        $this->actingAs($this->expert)->get("/calculations?period={$this->q1->id}")
            ->assertInertia(fn (Assert $p) => $p->has('results', 1)->where('results.0.userId', $this->expert->id)->where('run.summary', null));
        $this->actingAs($this->expert)->get("/calculations/results/{$mine->id}")->assertOk()
            ->assertInertia(fn (Assert $p) => $p->has('lines', 1)->where('lines.0.serial', 'S1'));
        $this->actingAs($this->expert)->get("/calculations/results/{$theirs->id}")->assertNotFound(); // AT-08

        $this->actingAs($this->head)->get("/calculations/results/{$mine->id}")->assertOk();
        $this->actingAs($this->head)->get("/calculations/results/{$theirs->id}")->assertNotFound();

        $this->actingAs($this->expert)->get("/dashboard?period={$this->q1->id}")
            ->assertInertia(fn (Assert $p) => $p->where('calc.mine.userId', $this->expert->id)->where('calc.mine.achievement', '0.900000'));
    }

    public function test_customer_category_change_is_audited(): void
    {
        $c = Customer::create(['tenant_id' => $this->admin->tenant_id, 'code' => 'REP', 'name' => 'نماینده']);
        $this->actingAs($this->expert)->put("/customers/{$c->id}", ['category' => 'rep_contract'])->assertForbidden();
        $this->actingAs($this->finance)->put("/customers/{$c->id}", ['category' => 'rep_contract'])->assertSessionHasNoErrors();
        $this->assertSame('rep_contract', $c->fresh()->category);
        $this->assertTrue(AuditEvent::where('action', 'customer.category_changed')->exists());
    }
}
