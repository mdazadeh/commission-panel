<?php

namespace App\Services\Commission;

use App\Domain\Commission\ActivityInput;
use App\Domain\Commission\Calculator;
use App\Domain\Commission\DocumentInput;
use App\Domain\Commission\Money;
use App\Domain\Commission\PersonInput;
use App\Enums\Role;
use App\Models\ActionPlanItem;
use App\Models\ActivityEntry;
use App\Models\CalculationLine;
use App\Models\CalculationResult;
use App\Models\CalculationRun;
use App\Models\Customer;
use App\Models\FiscalPeriod;
use App\Models\RuleVersion;
use App\Models\SalesDocument;
use App\Models\Target;
use App\Models\User;
use App\Services\AuditLogger;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Builds calculator inputs from the database, runs the engine for every commission-earning user
 * of a period and stores an immutable, fully traced snapshot (brief AN-01…AN-12, AT-06).
 */
class CalculationService
{
    public function __construct(private AuditLogger $audit) {}

    public function ruleVersionFor(FiscalPeriod $period): RuleVersion
    {
        $versions = RuleVersion::withoutGlobalScopes()->where('tenant_id', $period->tenant_id)
            ->whereDate('effective_from', '<=', $period->starts_on)
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $period->starts_on))
            ->whereIn('status', ['approved', 'draft'])
            ->orderByRaw("CASE WHEN status = 'approved' THEN 0 ELSE 1 END")
            ->orderByDesc('effective_from')->get();

        return $versions->first() ?? throw new InvalidArgumentException('برای این دوره نسخه قوانین معتبری وجود ندارد.');
    }

    public function run(FiscalPeriod $period, User $by): CalculationRun
    {
        if ($period->isLocked()) {
            throw new InvalidArgumentException('دوره قفل شده است؛ محاسبه جدید مجاز نیست (AT-07).');
        }
        $rules = $this->ruleVersionFor($period);
        $calculator = new Calculator($rules->parameters);
        $inputs = $this->buildInputs($period, $rules->parameters);

        $hash = hash('sha256', serialize([$rules->id, $rules->updated_at?->toIso8601String(), $inputs]));

        return DB::transaction(function () use ($period, $by, $rules, $calculator, $inputs, $hash) {
            $run = CalculationRun::create([
                'tenant_id' => $period->tenant_id,
                'fiscal_period_id' => $period->id,
                'rule_version_id' => $rules->id,
                'status' => 'provisional',
                'inputs_hash' => $hash,
                'created_by' => $by->id,
            ]);

            $totals = ['people' => 0, 'payable' => BigDecimal::zero(), 'sales' => BigDecimal::zero(), 'flags' => 0];
            foreach ($inputs as $in) {
                $r = $calculator->calculate($in);
                CalculationResult::create([
                    'calculation_run_id' => $run->id,
                    'user_id' => $in->userId,
                    'role' => $in->role,
                    'sales_total' => $r->salesTotal,
                    'target_total' => $r->targetTotal,
                    'achievement' => $r->achievement,
                    'tier_rate' => $r->tierRate,
                    'final_amount' => $r->final,
                    'payable_amount' => $r->payable,
                    'components' => ['items' => $r->components, 'next_tier' => $r->nextTier],
                    'flags' => $r->flags,
                ]);
                foreach (array_chunk($r->lines, 500) as $chunk) {
                    CalculationLine::insert(array_map(fn ($l) => [
                        'calculation_run_id' => $run->id,
                        'user_id' => $in->userId,
                        'sales_document_id' => $l['document_id'],
                        'category' => $l['category'],
                        'net_amount' => $l['net'],
                        'cash_amount' => $l['cash'],
                        'credit_amount' => $l['credit'],
                        'rate' => $l['rate'],
                        'commission' => $l['commission'],
                        'note' => $l['note'],
                    ], $chunk));
                }
                $totals['people']++;
                $totals['payable'] = $totals['payable']->plus($r->payable);
                if ($in->role === Role::SalesExpert->value) {
                    $totals['sales'] = $totals['sales']->plus($r->salesTotal);
                }
                $totals['flags'] += count($r->flags);
            }

            $summary = [
                'people' => $totals['people'],
                'payable_total' => (string) $totals['payable'],
                'expert_sales_total' => (string) $totals['sales'],
                'flags' => $totals['flags'],
                'rule_code' => $rules->code,
                'rule_status' => $rules->status,
            ];
            $run->update(['summary' => $summary]);
            $this->audit->log('calculation.run', $run, null, $summary + ['period' => $period->label(), 'inputs_hash' => $hash]);

            return $run;
        });
    }

    /** @return list<PersonInput> */
    public function buildInputs(FiscalPeriod $period, array $params): array
    {
        $tenantId = $period->tenant_id;
        $jy = $period->jalali_year;
        $months = range(($period->quarter - 1) * 3 + 1, $period->quarter * 3);

        $users = User::where('tenant_id', $tenantId)->where('is_active', true)
            ->whereIn('role', [Role::SalesExpert->value, Role::SalesHead->value])->orderBy('id')->get();
        $targets = Target::withoutGlobalScopes()->where('fiscal_period_id', $period->id)->get()->keyBy('user_id');
        $contractCustomers = Customer::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('category', 'rep_contract')->pluck('code')->flip();

        $docTypes = ['sale', 'return'];
        if ($params['sales_types']['include_non_operational'] ?? false) {
            $docTypes[] = 'non_operational';
        }
        $docs = SalesDocument::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('fiscal_period_id', $period->id)
            ->whereIn('doc_type', $docTypes)->whereNotNull('user_id')->orderBy('id')->get()->groupBy('user_id');

        $approvedPlans = ActionPlanItem::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('jalali_year', $jy)
            ->whereIn('jalali_month', $months)->where('status', 'approved')->get()
            ->mapWithKeys(fn ($i) => ["{$i->user_id}|{$i->jalali_month}|{$i->customer_code}" => true]);

        $activity = ActivityEntry::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('jalali_year', $jy)
            ->whereIn('jalali_month', $months)->get()->groupBy('user_id');

        $inputs = [];
        foreach ($users as $user) {
            $t = $targets->get($user->id);
            // Only people who have a target for the period or own sales in it get a result.
            if (! $t && ! $docs->has($user->id)) {
                continue;
            }
            $activityMonths = [];
            foreach ($activity->get($user->id, collect()) as $e) {
                $activityMonths[$e->jalali_month][$e->indicator] = ['target' => $e->target, 'actual' => $e->actual, 'status' => $e->status];
            }

            $common = [
                'userId' => $user->id,
                'role' => $user->role->value,
                'quarterTarget' => (string) ($t->quarter_target ?? '0'),
                'carriedOver' => $this->carriedOver($user, $period, $t),
                'lostSales' => (string) ($t->lost_sales ?? '0'),
                'priorReceivables' => (string) ($t->prior_receivables ?? '0'),
                'activity' => new ActivityInput($activityMonths),
                'periodMonths' => $months,
            ];

            if ($user->role === Role::SalesHead) {
                $teamIds = $user->team_id
                    ? $users->where('team_id', $user->team_id)->pluck('id')->push($user->id)->unique()
                    : collect([$user->id]);
                $teamSales = BigDecimal::zero();
                foreach ($teamIds as $id) {
                    foreach ($docs->get($id, collect()) as $d) {
                        $teamSales = $teamSales->plus((string) $d->net_amount);
                    }
                }
                $inputs[] = new PersonInput(...$common, documents: [], teamSales: (string) $teamSales);

                continue;
            }

            $documents = [];
            foreach ($docs->get($user->id, collect()) as $d) {
                $month = (int) explode('/', $d->issued_on_jalali)[1];
                $category = isset($contractCustomers[$d->customer_code]) ? 'rep_contract'
                    : (isset($approvedPlans["{$user->id}|{$month}|{$d->customer_code}"]) ? 'action_plan' : 'rep_no_contract');
                $documents[] = new DocumentInput(
                    $d->id, $category, (string) $d->net_amount, (string) $d->gross_amount, (string) $d->discount_amount,
                    (string) $d->cash_amount, (string) $d->credit_amount, $month,
                    $d->doc_type === 'sale' ? null : $d->doc_type,
                );
            }
            $inputs[] = new PersonInput(...$common, documents: $documents);
        }

        return $inputs;
    }

    /**
     * TG-03: unmet target moves to the next quarter of the same year.
     * Manual override wins; otherwise derived from the latest run of the previous quarter.
     */
    public function carriedOver(User $user, FiscalPeriod $period, ?Target $target): string
    {
        if ($target?->carried_over_override !== null) {
            return (string) $target->carried_over_override;
        }
        if ($period->quarter === 1) {
            return '0';
        }
        $previous = FiscalPeriod::withoutGlobalScopes()->where([
            'tenant_id' => $period->tenant_id, 'jalali_year' => $period->jalali_year, 'quarter' => $period->quarter - 1,
        ])->first();
        $run = $previous ? CalculationRun::withoutGlobalScopes()->where('fiscal_period_id', $previous->id)->latest('id')->first() : null;
        $result = $run?->results()->where('user_id', $user->id)->first();
        if (! $result) {
            return '0';
        }
        $lost = $result->components['items']['lost_sales']['value'] ?? '0';
        $remaining = Money::of((string) $result->target_total)->minus((string) $result->sales_total)->minus($lost);

        return $remaining->isPositive() ? (string) $remaining->toScale(0) : '0';
    }
}
