<?php

namespace Tests\Unit\Commission;

use App\Domain\Commission\ActivityInput;
use App\Domain\Commission\Calculator;
use App\Domain\Commission\DocumentInput;
use App\Domain\Commission\PersonInput;
use PHPUnit\Framework\TestCase;

/**
 * Golden tests: every expected figure is worked out by hand in the comments,
 * independently of the implementation.
 */
class CalculatorTest extends TestCase
{
    private static function params(): array
    {
        return json_decode(file_get_contents(__DIR__.'/../../../database/data/rule-version-1405-v1.json'), true);
    }

    /** @param array<int,array<string,array{0:int,1:int,2?:string}>> $spec month => indicator => [actual, target, status] */
    private static function activity(array $spec): ActivityInput
    {
        $months = [];
        foreach ($spec as $m => $indicators) {
            foreach ($indicators as $ind => $e) {
                $months[$m][$ind] = ['actual' => $e[0], 'target' => $e[1], 'status' => $e[2] ?? 'approved'];
            }
        }

        return new ActivityInput($months);
    }

    private static function fullActivity(array $override = []): ActivityInput
    {
        $spec = [];
        foreach ([1, 2, 3] as $m) {
            $spec[$m] = ['customer_meetings' => [12, 12], 'action_plan' => [10, 10], 'internal_meetings' => [4, 4]];
        }

        return self::activity(array_replace_recursive($spec, $override));
    }

    private static function doc(int $id, string $cat, string $net, string $cash, string $credit, int $month, string $discount = '0'): DocumentInput
    {
        $gross = (string) ((int) $net + (int) $discount);

        return new DocumentInput($id, $cat, $net, $gross, $discount, $cash, $credit, $month);
    }

    private static function expert(array $docs, ActivityInput $activity, string $target = '10000000000', string $receivables = '0', string $lost = '0'): PersonInput
    {
        return new PersonInput(1, 'sales_expert', $target, '0', $lost, $receivables, $docs, $activity, [1, 2, 3]);
    }

    public function test_full_expert_scenario(): void
    {
        // target 10bn; sales 9bn → 90% → tier 1.8% (> 85%)
        $docs = [
            self::doc(1, 'action_plan', '6000000000', '4000000000', '2000000000', 1),
            self::doc(2, 'rep_no_contract', '2000000000', '1000000000', '1000000000', 2),
            self::doc(3, 'rep_contract', '1000000000', '1000000000', '0', 2),
        ];
        // customer meetings: m1 12/12 → 0; m2 8/12 (<70%, month sales 3bn < 3.33bn) → 20%; m3 10/12 → 10% ⇒ 30%
        $activity = self::fullActivity([2 => ['customer_meetings' => [8, 12]], 3 => ['customer_meetings' => [10, 12]]]);

        $r = (new Calculator(self::params()))->calculate(self::expert($docs, $activity, receivables: '500000000'));
        $c = fn ($k) => $r->components[$k]['value'];

        $this->assertSame('0.900000', $r->achievement);
        $this->assertSame('0.018', $r->tierRate);
        $this->assertSame('10000000.0000', $c('base_rep_no_contract'));   // 2bn × 0.5%
        $this->assertSame('10000000.0000', $c('base_rep_contract'));      // 1bn × 1%
        $this->assertSame('72000000.0000', $c('base_action_plan_cash'));  // 4bn × 1.8%
        $this->assertSame('36000000.0000', $c('base_action_plan_credit')); // 2bn × 1.8%
        $this->assertSame('90000000.0000', $c('inc_low_discount'));       // 9bn × (10% − 0) × 10%
        $this->assertSame('5000000.0000', $c('ded_prior_receivables'));   // 500m × 1%
        $this->assertSame('14350000.0000', $c('ded_non_cash'));           // (1bn × 0.5% + 36m) × 35%
        $this->assertSame('0.0000', $c('ded_high_discount'));
        $this->assertSame('188650000.0000', $c('deductible_net'));        // 10+72+36+90−5−14.35
        $this->assertSame('0.30', $c('activity_rate'));
        $this->assertSame('56595000.0000', $c('ded_activity'));           // 188.65m × 30%
        $this->assertSame('142055000.0000', $r->final);                  // 10m (contract, exempt) + 188.65m − 56.595m
        $this->assertSame('142000000', $r->payable);                     // D-06
        $this->assertSame(['above' => '0.95', 'rate' => '0.020', 'sales_needed' => '500000001'], $r->nextTier);
        $this->assertCount(3, $r->lines);
        $this->assertSame([], $r->flags);
    }

    public function test_gate_at_exactly_fifty_percent_pays_nothing(): void
    {
        $docs = [self::doc(1, 'rep_contract', '5000000000', '5000000000', '0', 1)];
        $r = (new Calculator(self::params()))->calculate(self::expert($docs, self::fullActivity()));

        $this->assertSame('0.500000', $r->achievement);
        $this->assertSame('0', $r->tierRate);
        $this->assertSame('0.0000', $r->final);
        $this->assertSame('gate_failed', $r->flags[0]['code']);
        $this->assertSame('1', $r->nextTier['sales_needed']); // one rial above 50%
    }

    public function test_lost_sales_count_for_tier_but_not_for_base(): void
    {
        // 4.5bn sold + 1bn lost = 55% → passes gate, tier 1%; base only on sold amount
        $docs = [self::doc(1, 'action_plan', '4500000000', '4500000000', '0', 1, '500000000')];
        $r = (new Calculator(self::params()))->calculate(self::expert($docs, self::fullActivity(), lost: '1000000000'));

        $this->assertSame('0.010', $r->tierRate);
        $this->assertSame('45000000.0000', $r->components['base_action_plan_cash']['value']);
    }

    public function test_high_discount_deduction_spares_contract_reps(): void
    {
        // gross 7.5bn, discount 1.5bn → 20% avg discount (> 15%) ; sales 6bn of 6bn target → 100% → tier 2.0%
        $docs = [
            self::doc(1, 'action_plan', '5000000000', '5000000000', '0', 1, '1250000000'),
            self::doc(2, 'rep_contract', '1000000000', '1000000000', '0', 1, '250000000'),
        ];
        $r = (new Calculator(self::params()))->calculate(self::expert($docs, self::fullActivity(), target: '6000000000'));

        $this->assertSame('0.200000', $r->components['avg_discount']['value']);
        $this->assertSame('25000000.0000', $r->components['ded_high_discount']['value']); // 25% × 100m (Z) — Y excluded
        $this->assertSame('85000000.0000', $r->final); // 10m + 100m − 25m
    }

    public function test_monthly_target_met_halves_activity_penalty(): void
    {
        // month 1 sales 4bn ≥ 10bn/3 → a <70% month costs 10% instead of 20%
        $docs = [self::doc(1, 'action_plan', '9000000000', '9000000000', '0', 1)];
        $r = (new Calculator(self::params()))->calculate(self::expert($docs, self::fullActivity([1 => ['customer_meetings' => [2, 12]]])));

        $this->assertSame('0.10', $r->components['activity_rate']['value']);
    }

    public function test_pending_activity_is_not_counted_and_flagged(): void
    {
        $docs = [self::doc(1, 'action_plan', '9000000000', '9000000000', '0', 1)];
        $r = (new Calculator(self::params()))->calculate(self::expert($docs, self::fullActivity([3 => ['internal_meetings' => [0, 4, 'pending']]])));

        $this->assertSame('0', $r->components['activity_rate']['value']);
        $this->assertSame('activity_incomplete', $r->flags[0]['code']);
    }

    public function test_return_reduces_sales_and_commission(): void
    {
        $docs = [
            self::doc(1, 'action_plan', '9000000000', '9000000000', '0', 1),
            self::doc(2, 'action_plan', '-1000000000', '-1000000000', '0', 2),
        ];
        $r = (new Calculator(self::params()))->calculate(self::expert($docs, self::fullActivity()));

        $this->assertSame('8000000000', $r->salesTotal);
        $this->assertSame('0.016', $r->tierRate); // 80% → 1.6%
        $this->assertSame('128000000.0000', $r->components['base_action_plan_cash']['value']); // 144m − 16m
    }

    public function test_sales_head(): void
    {
        // team 12bn on 10bn target = 120% → not > 120% → 0.6% ; and inside the unapproved 110–120 band
        $in = new PersonInput(9, 'sales_head', '10000000000', '0', '0', '1000000000', [], self::fullActivity(), [1, 2, 3], '12000000000');
        $r = (new Calculator(self::params()))->calculate($in);

        $this->assertSame('0.006', $r->tierRate);
        $this->assertSame('72000000.0000', $r->components['base_team']['value']);
        $this->assertSame('5000000.0000', $r->components['ded_prior_receivables']['value']); // 1bn × 0.5%
        $this->assertSame('67000000.0000', $r->final);
        $this->assertContains('provisional_band', array_column($r->flags, 'code'));
        $this->assertContains('pending_rules', array_column($r->flags, 'code'));
    }

    /**
     * Parity with the corrected Excel logic for marketer code 66, spring 1405 (docs/rules-catalog.md §8.1):
     * old Excel parameters (no gate, 0.5% floor tier, 10% discount threshold, non-cash on action plan only).
     */
    public function test_parity_with_corrected_excel_for_code_66(): void
    {
        $p = self::params();
        $p['minimum_achievement_gate']['above'] = '0';
        array_unshift($p['tiers']['sales_expert'], ['above' => '0', 'rate' => '0.005']);
        $p['deductions']['high_discount']['avg_discount_above'] = '0.10';
        $p['deductions']['non_cash']['scope'] = 'action_plan_only';

        $docs = [
            new DocumentInput(1, 'rep_no_contract', '2429000000', '2429000000', '0', '530000000', '1899000000', 1),
            new DocumentInput(2, 'action_plan', '7933600000', '8651000000', '717400000', '5950200000', '1983400000', 2),
        ];
        // activity 40% as entered in the sheet: two months below 70%, monthly target not met
        $activity = self::fullActivity([1 => ['customer_meetings' => [0, 12]], 2 => ['customer_meetings' => [0, 12]]]);
        $in = new PersonInput(66, 'sales_expert', '90000000000', '0', '0', '0', $docs, $activity, [1, 2, 3]);

        $r = (new Calculator($p))->calculate($in);

        $this->assertSame('12145000.0000', $r->components['base_rep_no_contract']['value']);
        $this->assertSame('29751000.0000', $r->components['base_action_plan_cash']['value']);
        $this->assertSame('9917000.0000', $r->components['base_action_plan_credit']['value']);
        $this->assertSame('3470950.0000', $r->components['ded_non_cash']['value']);
        $this->assertSame('0.0000', $r->components['inc_low_discount']['value']); // avg discount ≈ 6.5% — the Excel sheet wrongly paid this
        $this->assertSame('29005230.0000', $r->final); // Excel paid 62,175,600
    }
}
