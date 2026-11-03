<?php

namespace App\Domain\Commission;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/** Decimal helpers. Amounts are rial; intermediate commission values keep 4 decimals, no floats anywhere. */
final class Money
{
    public const SCALE = 4;

    public static function of(string|int|BigDecimal|null $v): BigDecimal
    {
        return $v instanceof BigDecimal ? $v : BigDecimal::of($v === null || $v === '' ? '0' : (string) $v);
    }

    public static function mul(BigDecimal|string $a, BigDecimal|string $b): BigDecimal
    {
        return self::of($a)->multipliedBy(self::of($b))->toScale(self::SCALE, RoundingMode::HalfUp);
    }

    public static function str(BigDecimal $v): string
    {
        return (string) $v->toScale(self::SCALE, RoundingMode::HalfUp);
    }

    /** a / b with 6 decimals (ratios), 0 when b is 0. */
    public static function ratio(BigDecimal|string $a, BigDecimal|string $b): BigDecimal
    {
        $b = self::of($b);

        return $b->isZero() ? BigDecimal::zero() : self::of($a)->dividedBy($b, 6, RoundingMode::HalfUp);
    }

    public static function max(BigDecimal $a, BigDecimal $b): BigDecimal
    {
        return $a->isGreaterThan($b) ? $a : $b;
    }

    /** D-06: nearest 1,000,000 rial, half up. */
    public static function roundToMillion(BigDecimal $v): BigDecimal
    {
        return $v->dividedBy(1_000_000, 0, RoundingMode::HalfUp)->multipliedBy(1_000_000);
    }
}
