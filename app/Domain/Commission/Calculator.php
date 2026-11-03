<?php

namespace App\Domain\Commission;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Pure commission calculator — no database, no clock. Implements docs/rules-catalog.md
 * (decisions D-01 … D-12) driven by the parameters of a RuleVersion.
 *
 * Excel column letters in comments refer to the «پورسانت» sheet of the spring-1405 workbook.
 */
final class Calculator
{
    private const INDICATOR_LABELS = [
        'customer_meetings' => 'جلسات با مشتری',
        'action_plan' => 'فعالیت اکشن‌پلن',
        'internal_meetings' => 'جلسات درون‌سازمانی',
    ];

    /** @param array<string,mixed> $params RuleVersion::parameters */
    public function __construct(private readonly array $params) {}

    public function calculate(PersonInput $in): Result
    {
        return $in->role === 'sales_head' ? $this->head($in) : $this->expert($in);
    }

    private function expert(PersonInput $in): Result
    {
        $r = new Result;
        $p = $this->params;

        $sales = BigDecimal::zero();
        foreach ($in->documents as $d) {
            $sales = $sales->plus($d->net);
        }
        $lost = Money::of($in->lostSales);
        $target = Money::of($in->quarterTarget)->plus($in->carriedOver);
        $forTier = $sales->plus($lost);

        $r->salesTotal = (string) $sales;
        $r->targetTotal = (string) $target;
        $r->achievement = (string) Money::ratio($forTier, $target);

        $r->add('sales_total', 'جمع فروش فصل', (string) $sales, ['formula' => 'Σ مبلغ خالص اسناد (C)', 'rule' => 'D-11']);
        $r->add('lost_sales', 'فروش از دست رفته کالای فاران', (string) $lost, ['rule' => 'AC-02']);
        $r->add('quarter_target', 'تارگت فصل', (string) Money::of($in->quarterTarget), ['rule' => 'TG-02']);
        $r->add('carried_over', 'تارگت معوق', (string) Money::of($in->carriedOver), ['rule' => 'TG-03']);
        $r->add('achievement', 'درصد تحقق', $r->achievement, ['formula' => '(فروش + از دست رفته) ÷ (تارگت فصل + معوق)', 'rule' => 'D-01']);

        if ($target->isZero()) {
            $r->flag('no_target', 'برای این فرد تارگت ثبت نشده است؛ پورسانت محاسبه نمی‌شود.');
        }

        $tiers = $p['tiers']['sales_expert'];
        $tierRate = $this->tierRate($tiers, $forTier, $target);
        $gateAbove = (string) $p['minimum_achievement_gate']['above'];
        $gatePassed = ! $target->isZero() && $forTier->isGreaterThan(Money::of($gateAbove)->multipliedBy($target));
        $r->tierRate = (string) $tierRate;
        $r->add('tier_rate', 'نرخ Tier', (string) $tierRate, ['rule' => 'AC-05']);
        $r->nextTier = $this->nextTier($tiers, $forTier, $target);

        // Per-document base commission.
        $rates = [
            'rep_no_contract' => Money::of($p['sales_types']['rep_no_contract']['rate']),
            'rep_contract' => Money::of($p['sales_types']['rep_contract']['rate']),
        ];
        $x = $y = $z = $aa = BigDecimal::zero();
        $nonLabelCredit = BigDecimal::zero();
        $gross = $discount = BigDecimal::zero();
        $monthSales = [];

        foreach ($in->documents as $d) {
            $gross = $gross->plus($d->gross);
            $discount = $discount->plus($d->discount);
            $monthSales[$d->month] = ($monthSales[$d->month] ?? BigDecimal::zero())->plus($d->net);

            if ($d->category === 'action_plan') {
                $cash = Money::mul($d->cash, $tierRate);
                $credit = Money::mul($d->credit, $tierRate);
                $z = $z->plus($cash);
                $aa = $aa->plus($credit);
                $commission = $cash->plus($credit);
                $rate = $tierRate;
            } else {
                $rate = $rates[$d->category];
                $commission = Money::mul($d->net, $rate);
                if ($d->category === 'rep_contract') {
                    $y = $y->plus($commission);
                } else {
                    $x = $x->plus($commission);
                    $nonLabelCredit = $nonLabelCredit->plus($d->credit);
                }
            }
            $r->lines[] = [
                'document_id' => $d->id, 'category' => $d->category, 'net' => $d->net, 'cash' => $d->cash,
                'credit' => $d->credit, 'rate' => (string) $rate, 'commission' => Money::str($commission), 'note' => $d->note,
            ];
        }

        $r->add('base_rep_no_contract', 'پورسانت نماینده بدون قرارداد / غیر لیبل', Money::str($x), ['rate' => (string) $rates['rep_no_contract'], 'formula' => 'X', 'rule' => 'BS-02']);
        $r->add('base_rep_contract', 'پورسانت نماینده با قرارداد (خالص)', Money::str($y), ['rate' => (string) $rates['rep_contract'], 'formula' => 'Y — معاف از همه کسورات', 'rule' => 'D-10']);
        $r->add('base_action_plan_cash', 'پورسانت نقد اکشن‌پلن', Money::str($z), ['rate' => (string) $tierRate, 'formula' => 'Z', 'rule' => 'BS-01']);
        $r->add('base_action_plan_credit', 'پورسانت اعتباری اکشن‌پلن', Money::str($aa), ['rate' => (string) $tierRate, 'formula' => 'AA', 'rule' => 'BS-01']);

        // Deductions & incentives.
        $avgDiscount = Money::ratio($discount, $gross);
        $r->add('avg_discount', 'میانگین تخفیف', (string) $avgDiscount, ['formula' => 'Σ تخفیف ÷ Σ مبلغ کل (AB)']);

        $hd = $p['deductions']['high_discount'];
        $ac = $avgDiscount->isGreaterThan($hd['avg_discount_above'])
            ? Money::mul($x->plus($z)->plus($aa), $hd['rate']) : BigDecimal::zero();
        $r->add('ded_high_discount', 'کسر بالا بودن تخفیف', Money::str($ac), ['rate' => $hd['rate'], 'formula' => "اگر میانگین تخفیف > {$hd['avg_discount_above']}: (X+Z+AA) × نرخ", 'rule' => 'D-03']);

        $ae = Money::mul($in->priorReceivables, $p['deductions']['prior_receivables']['sales_expert']);
        $r->add('ded_prior_receivables', 'کسر مطالبات فصول گذشته', Money::str($ae), ['base' => (string) Money::of($in->priorReceivables), 'rate' => $p['deductions']['prior_receivables']['sales_expert'], 'rule' => 'DD-03']);

        $nc = $p['deductions']['non_cash'];
        // D-04: all non-cash sales except contract reps. 'action_plan_only' reproduces the old Excel sheet (parity tests).
        $nonCashBase = ($nc['scope'] ?? '') === 'action_plan_only'
            ? $aa
            : Money::mul($nonLabelCredit, $rates['rep_no_contract'])->plus($aa);
        $af = Money::mul($nonCashBase, $nc['rate']);
        $r->add('ded_non_cash', 'کسر فروش غیرنقد (تعهدی)', Money::str($af), ['base' => Money::str($nonCashBase), 'rate' => $nc['rate'], 'formula' => 'پورسانت سهم تعهدی همه فروش‌ها به‌جز نماینده با قرارداد × نرخ', 'rule' => 'D-04']);

        $ld = $p['incentives']['low_discount'];
        $ag = $avgDiscount->isLessThan($ld['avg_discount_below'])
            ? Money::mul(Money::mul($sales, Money::of($ld['reference_discount'])->minus($avgDiscount)), $ld['share_sales_expert'])
            : BigDecimal::zero();
        $r->add('inc_low_discount', 'مشوق تخفیف پایین', Money::str($ag), ['formula' => "اگر میانگین تخفیف < {$ld['avg_discount_below']}: فروش × ({$ld['reference_discount']} − تخفیف) × {$ld['share_sales_expert']}", 'rule' => 'IN-01']);

        $deductible = $x->plus($z)->plus($aa)->plus($ag)->minus($ac)->minus($ae)->minus($af);
        $r->add('deductible_net', 'پورسانت مشمول کسر فعالیت', Money::str($deductible), ['formula' => 'X + Z + AA + مشوق − کسورات']);

        [$activityRate] = $this->activity($in, $r, $target, $monthSales);
        $am = $deductible->isPositive() ? Money::mul($deductible, $activityRate) : BigDecimal::zero();
        $r->add('ded_activity', 'کسر عدم فعالیت', Money::str($am), ['rate' => (string) $activityRate, 'rule' => 'D-02']);

        $final = $y->plus($deductible)->minus($am);
        if (! $gatePassed) {
            $r->flag('gate_failed', "تحقق تارگت {$gateAbove} یا کمتر است؛ طبق D-08 کل پورسانت صفر است.");
            $final = BigDecimal::zero();
        }

        return $this->finish($r, $final);
    }

    private function head(PersonInput $in): Result
    {
        $r = new Result;
        $p = $this->params;

        $sales = Money::of($in->teamSales);
        $lost = Money::of($in->lostSales);
        $target = Money::of($in->quarterTarget)->plus($in->carriedOver);
        $forTier = $sales->plus($lost);
        $r->salesTotal = (string) $sales;
        $r->targetTotal = (string) $target;
        $r->achievement = (string) Money::ratio($forTier, $target);

        $r->add('team_sales', 'جمع فروش تیم', (string) $sales, ['rule' => 'D-09']);
        $r->add('lost_sales', 'فروش از دست رفته تیم', (string) $lost, ['rule' => 'AC-02']);
        $r->add('quarter_target', 'تارگت فصل رئیس', (string) Money::of($in->quarterTarget), ['rule' => 'TG-05']);
        $r->add('carried_over', 'تارگت معوق', (string) Money::of($in->carriedOver), ['rule' => 'TG-03']);
        $r->add('achievement', 'درصد تحقق تیم', $r->achievement, ['rule' => 'D-09']);

        if ($target->isZero()) {
            $r->flag('no_target', 'برای این رئیس تارگت ثبت نشده است.');
        }

        $tiers = $p['tiers']['sales_head'];
        $rate = $this->tierRate($tiers, $forTier, $target);
        $r->tierRate = (string) $rate;
        $r->nextTier = $this->nextTier($tiers, $forTier, $target);
        if ($forTier->isGreaterThan(Money::of('1.10')->multipliedBy($target)) && ! $forTier->isGreaterThan(Money::of('1.20')->multipliedBy($target))) {
            $r->flag('provisional_band', 'نرخ بازه ۱۱۰ تا ۱۲۰٪ رئیس هنوز مصوب نشده است (D-07).');
        }

        $base = Money::mul($sales, $rate);
        $r->add('base_team', 'پورسانت فروش تیم', Money::str($base), ['rate' => (string) $rate, 'formula' => 'فروش تیم × نرخ Tier رئیس', 'rule' => 'D-09']);

        $ae = Money::mul($in->priorReceivables, $p['deductions']['prior_receivables']['sales_head']);
        $r->add('ded_prior_receivables', 'کسر مطالبات معوق تیم', Money::str($ae), ['base' => (string) Money::of($in->priorReceivables), 'rate' => $p['deductions']['prior_receivables']['sales_head'], 'rule' => 'DD-03']);

        $deductible = $base->minus($ae);
        [$activityRate] = $this->activity($in, $r, $target, []);
        $am = $deductible->isPositive() ? Money::mul($deductible, $activityRate) : BigDecimal::zero();
        $r->add('ded_activity', 'کسر عدم فعالیت', Money::str($am), ['rate' => (string) $activityRate, 'rule' => 'D-02']);

        $r->flag('pending_rules', 'مشوق تخفیف پایین رئیس (۲٫۵٪) و کسر ۵۰٪ نداشتن اکشن‌پلن تأییدشده هنوز اعمال نشده‌اند.');

        return $this->finish($r, $deductible->minus($am));
    }

    /**
     * D-02: per indicator & month — ratio < 0.70 → 20% (10% if that month's sales target was met),
     * 0.70 ≤ ratio < 1 → 10%, otherwise 0. Quarter = sum of months; the worst indicator applies.
     * Entries that are not approved are not counted and flag the result (AT-04).
     *
     * @param  array<int, BigDecimal>  $monthSales
     * @return array{0: BigDecimal}
     */
    private function activity(PersonInput $in, Result $r, BigDecimal $target, array $monthSales): array
    {
        $bands = $this->params['activity']['monthly_bands'];
        $indicators = $this->params['activity']['indicators'];
        $monthlyTarget = $target->dividedBy(3, 0, RoundingMode::HalfUp);
        $worst = BigDecimal::zero();
        $incomplete = [];
        $detail = [];

        foreach ($indicators as $indicator) {
            $label = self::INDICATOR_LABELS[$indicator] ?? $indicator;
            $sum = BigDecimal::zero();
            foreach ($in->periodMonths as $m) {
                $e = $in->activity->months[$m][$indicator] ?? null;
                if (! $e || $e['status'] !== 'approved') {
                    $incomplete[] = $m;
                    $detail[] = "{$label} ماه {$m}: ".($e ? 'منتظر تأیید' : 'ثبت نشده');

                    continue;
                }
                $ratio = $e['target'] > 0 ? Money::ratio((string) $e['actual'], (string) $e['target']) : BigDecimal::one();
                $met = ! $target->isZero() && ($monthSales[$m] ?? BigDecimal::zero())->isGreaterThanOrEqualTo($monthlyTarget);
                $deduction = BigDecimal::zero();
                foreach ($bands as $band) {
                    if ($ratio->isLessThan($band['below'])) {
                        $deduction = Money::of($met && isset($band['deduction_if_monthly_target_met']) ? $band['deduction_if_monthly_target_met'] : $band['deduction']);
                        break;
                    }
                }
                $sum = $sum->plus($deduction);
                $detail[] = "{$label} ماه {$m}: {$e['actual']}/{$e['target']} → کسر {$deduction}".($met ? ' (تارگت ماه محقق)' : '');
            }
            $r->add("activity_{$indicator}", "کسر فعالیت — {$label}", (string) $sum, ['rule' => 'D-02']);
            $worst = Money::max($worst, $sum);
        }
        if ($worst->isGreaterThan(1)) {
            $worst = BigDecimal::one();
        }
        $r->add('activity_rate', 'درصد کسر فعالیت (بدترین شاخص)', (string) $worst, ['formula' => implode(' | ', $detail), 'rule' => 'D-02']);

        if ($incomplete) {
            $months = implode('، ', array_unique($incomplete));
            $r->flag('activity_incomplete', "فعالیت ماه‌های {$months} هنوز تأیید نشده و در کسر فعالیت حساب نشده است؛ مبلغ ممکن است کاهش یابد.");
        }

        return [$worst];
    }

    private function tierRate(array $tiers, BigDecimal $amount, BigDecimal $target): BigDecimal
    {
        $rate = BigDecimal::zero();
        if ($target->isZero()) {
            return $rate;
        }
        foreach ($tiers as $t) {
            if ($amount->isGreaterThan(Money::of($t['above'])->multipliedBy($target))) {
                $rate = Money::of($t['rate']);
            }
        }

        return $rate;
    }

    /** @return array{above:string, rate:string, sales_needed:string}|null */
    private function nextTier(array $tiers, BigDecimal $amount, BigDecimal $target): ?array
    {
        if ($target->isZero()) {
            return null;
        }
        foreach ($tiers as $t) {
            $threshold = Money::of($t['above'])->multipliedBy($target);
            if (! $amount->isGreaterThan($threshold)) {
                // strictly greater → one rial above the threshold
                $needed = $threshold->minus($amount)->toScale(0, RoundingMode::Floor)->plus(1);

                return ['above' => (string) $t['above'], 'rate' => (string) $t['rate'], 'sales_needed' => (string) $needed];
            }
        }

        return null;
    }

    private function finish(Result $r, BigDecimal $final): Result
    {
        $r->final = Money::str($final);
        $r->payable = (string) Money::roundToMillion($final);
        $r->add('final', 'پورسانت نهایی', $r->final, ['rule' => 'AN']);
        $r->add('payable', 'مبلغ قابل پرداخت (گرد به میلیون ریال)', $r->payable, ['rule' => 'D-06']);
        if ($final->isNegative()) {
            $r->flag('negative_result', 'کسورات از پورسانت بیشتر است؛ نحوه تسویه مانده منفی باید توسط مالی تعیین شود.');
        }

        return $r;
    }
}
