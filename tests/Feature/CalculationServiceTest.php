<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\ActionPlanItem;
use App\Models\ActivityEntry;
use App\Models\AuditEvent;
use App\Models\CalculationLine;
use App\Models\Customer;
use App\Models\FiscalPeriod;
use App\Models\ImportBatch;
use App\Models\SalesDocument;
use App\Models\Target;
use App\Models\Team;
use App\Models\User;
use App\Services\Commission\CalculationService;
use App\Support\Persian;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class CalculationServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private ImportBatch $batch;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->bootstrapTenant();
        $this->batch = ImportBatch::create(['tenant_id' => $this->admin->tenant_id, 'type' => 'sales', 'original_filename' => 'x', 'stored_path' => 'x', 'file_hash' => str_repeat('b', 64), 'status' => 'committed']);
    }

    private function period(int $q): FiscalPeriod
    {
        return FiscalPeriod::withoutGlobalScopes()->where(['jalali_year' => 1405, 'quarter' => $q])->firstOrFail();
    }

    private function doc(User $u, string $serial, string $jalali, string $customer, int $net, int $credit = 0, string $type = 'sale'): SalesDocument
    {
        $period = FiscalPeriod::withoutGlobalScopes()->whereDate('starts_on', '<=', Persian::jalaliToDate($jalali))
            ->whereDate('ends_on', '>=', Persian::jalaliToDate($jalali))->first();

        return SalesDocument::create([
            'tenant_id' => $u->tenant_id, 'import_batch_id' => $this->batch->id, 'doc_type' => $type, 'serial' => $serial,
            'issued_on' => Persian::jalaliToDate($jalali)->toDateString(), 'issued_on_jalali' => $jalali,
            'fiscal_period_id' => $period->id, 'customer_code' => $customer, 'marketer_code' => $u->marketer_code, 'user_id' => $u->id,
            'gross_amount' => $net, 'discount_amount' => 0, 'net_amount' => $net, 'cash_amount' => $net - $credit, 'credit_amount' => $credit,
            'row_hash' => hash('sha256', $serial.$jalali),
        ]);
    }

    private function approveAllActivity(User $u, array $months): void
    {
        foreach ($months as $m) {
            foreach (['customer_meetings' => 12, 'action_plan' => 5, 'internal_meetings' => 4] as $ind => $t) {
                ActivityEntry::create(['tenant_id' => $u->tenant_id, 'user_id' => $u->id, 'jalali_year' => 1405, 'jalali_month' => $m,
                    'indicator' => $ind, 'target' => $t, 'actual' => $t, 'status' => 'approved', 'reviewed_by' => $this->admin->id]);
            }
        }
    }

    public function test_classifies_documents_and_traces_every_line(): void
    {
        $team = Team::create(['tenant_id' => $this->admin->tenant_id, 'name' => 'دیجیتال', 'department' => 'digital']);
        $expert = $this->makeUser(Role::SalesExpert, ['marketer_code' => '24', 'team_id' => $team->id]);
        $head = $this->makeUser(Role::SalesHead, ['team_id' => $team->id]);
        $q1 = $this->period(1);
        Target::create(['tenant_id' => $expert->tenant_id, 'user_id' => $expert->id, 'fiscal_period_id' => $q1->id, 'quarter_target' => '10000000000']);
        Target::create(['tenant_id' => $expert->tenant_id, 'user_id' => $head->id, 'fiscal_period_id' => $q1->id, 'quarter_target' => '10000000000']);
        Customer::create(['tenant_id' => $expert->tenant_id, 'code' => 'CONTRACT', 'name' => 'نماینده', 'category' => 'rep_contract']);

        // Approved plan for PLAN in month 1 only; a submitted (unapproved) plan for PENDING.
        ActionPlanItem::create(['tenant_id' => $expert->tenant_id, 'user_id' => $expert->id, 'jalali_year' => 1405, 'jalali_month' => 1, 'customer_code' => 'PLAN', 'customer_name' => 'x', 'lead_status' => 'warm', 'status' => 'approved']);
        ActionPlanItem::create(['tenant_id' => $expert->tenant_id, 'user_id' => $expert->id, 'jalali_year' => 1405, 'jalali_month' => 1, 'customer_code' => 'PENDING', 'customer_name' => 'y', 'lead_status' => 'cold', 'status' => 'submitted']);

        $this->doc($expert, 'A', '1405/01/10', 'PLAN', 6000000000);
        $this->doc($expert, 'B', '1405/02/10', 'PLAN', 1000000000);     // plan was for month 1 → not action plan in month 2 (D-05)
        $this->doc($expert, 'C', '1405/01/12', 'PENDING', 1000000000);  // plan not approved → 0.5%
        $this->doc($expert, 'D', '1405/03/01', 'CONTRACT', 1000000000);
        $this->doc($expert, 'E', '1405/03/02', 'OTHER', 500000000, 0, 'non_operational'); // D-11: counted
        $this->approveAllActivity($expert, [1, 2, 3]);
        $this->approveAllActivity($head, [1, 2, 3]);

        $run = app(CalculationService::class)->run($q1, $this->admin);

        $lines = CalculationLine::where('calculation_run_id', $run->id)->get()->keyBy(fn ($l) => SalesDocument::withoutGlobalScopes()->find($l->sales_document_id)->serial);
        $this->assertSame(['action_plan', 'rep_no_contract', 'rep_no_contract', 'rep_contract', 'rep_no_contract'],
            [$lines['A']->category, $lines['B']->category, $lines['C']->category, $lines['D']->category, $lines['E']->category]);

        $r = $run->results()->where('user_id', $expert->id)->first();
        // sales 9.5bn / 10bn = 95% → not > 95% → tier 1.8%
        $this->assertSame('9500000000', (string) $r->sales_total);
        $this->assertSame(0.018, (float) $r->tier_rate);
        // X = 2.5bn × 0.5% = 12.5m ; Y = 10m ; Z = 6bn × 1.8% = 108m ; AG = 9.5bn × 10% × 10% = 95m ; AF = 0
        $this->assertSame('225500000.0000', $r->components['items']['final']['value']);
        $this->assertSame('226000000', (string) $r->payable_amount);

        $h = $run->results()->where('user_id', $head->id)->first();
        $this->assertSame('9500000000', (string) $h->sales_total); // team sales
        $this->assertSame('38000000.0000', $h->components['items']['final']['value']); // 9.5bn × 0.4% (head tier: > 85%, ≤ 95%)

        $this->assertSame('provisional', $run->status);
        $this->assertSame('draft', $run->summary['rule_status']);
        $this->assertTrue(AuditEvent::where('action', 'calculation.run')->exists());
    }

    public function test_unmet_target_carries_over_to_next_quarter(): void
    {
        $expert = $this->makeUser(Role::SalesExpert, ['marketer_code' => '24']);
        [$q1, $q2] = [$this->period(1), $this->period(2)];
        Target::create(['tenant_id' => $expert->tenant_id, 'user_id' => $expert->id, 'fiscal_period_id' => $q1->id, 'quarter_target' => '10000000000', 'lost_sales' => '1000000000']);
        Target::create(['tenant_id' => $expert->tenant_id, 'user_id' => $expert->id, 'fiscal_period_id' => $q2->id, 'quarter_target' => '20000000000']);
        $this->doc($expert, 'A', '1405/01/10', 'X', 7000000000);

        app(CalculationService::class)->run($q1, $this->admin);
        $run2 = app(CalculationService::class)->run($q2, $this->admin);

        $r = $run2->results()->where('user_id', $expert->id)->first();
        // Q1 remaining = 10bn − 7bn − 1bn lost = 2bn → Q2 target 20bn + 2bn
        $this->assertSame('2000000000', $r->components['items']['carried_over']['value']);
        $this->assertSame('22000000000', (string) $r->target_total);
    }

    public function test_locked_period_cannot_be_recalculated(): void
    {
        $q1 = $this->period(1);
        $q1->update(['status' => 'locked']);

        $this->expectException(InvalidArgumentException::class);
        app(CalculationService::class)->run($q1, $this->admin);
    }
}
