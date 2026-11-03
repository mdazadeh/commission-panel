<?php

namespace App\Support;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Morilog\Jalali\CalendarUtils;

/** Normalisation helpers for Persian data coming from Farapayam / Excel. */
final class Persian
{
    private const CHAR_MAP = [
        'ي' => 'ی', 'ى' => 'ی', 'ك' => 'ک', 'ة' => 'ه', 'ۀ' => 'ه', 'أ' => 'ا', 'إ' => 'ا',
        '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        "\u{00A0}" => ' ', "\u{200F}" => '', "\u{200E}" => '', "\u{FEFF}" => '',
    ];

    /** Arabic → Persian letters, any digits → Latin, collapse whitespace (ZWNJ kept). */
    public static function normalize(mixed $value): string
    {
        $s = strtr(trim((string) $value), self::CHAR_MAP);

        return trim(preg_replace('/[ \t\r\n]+/u', ' ', $s) ?? $s);
    }

    /** Header matching key: normalized, without ZWNJ/spaces. */
    public static function key(mixed $value): string
    {
        return str_replace(["\u{200C}", ' ', '_', '-'], '', self::normalize($value));
    }

    /**
     * Parse an amount to an integer-rial string. Accepts numbers (incl. float noise such as
     * 112035714999.99998), Persian digits and thousands separators. Rounds HALF_UP.
     */
    public static function toRial(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '0';
        }
        if (is_int($value)) {
            return (string) $value;
        }
        if (is_float($value)) {
            $value = sprintf('%.6F', $value);
        }
        $s = str_replace([',', '٬', '،', ' '], '', self::normalize($value));
        if (! preg_match('/^-?\d+(\.\d+)?$/', $s)) {
            throw new InvalidArgumentException("Invalid amount: {$value}");
        }

        return (string) BigDecimal::of($s)->toScale(0, RoundingMode::HalfUp);
    }

    /** Parse «1405/01/24» (or 1405-1-24) into a Gregorian date. */
    public static function jalaliToDate(mixed $value): CarbonImmutable
    {
        $s = self::normalize($value);
        if (! preg_match('#^(\d{4})[/\-.](\d{1,2})[/\-.](\d{1,2})$#', $s, $m)) {
            throw new InvalidArgumentException("Invalid Jalali date: {$value}");
        }
        [$jy, $jm, $jd] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        if (! CalendarUtils::checkDate($jy, $jm, $jd, true)) {
            throw new InvalidArgumentException("Invalid Jalali date: {$value}");
        }
        [$gy, $gm, $gd] = CalendarUtils::toGregorian($jy, $jm, $jd);

        return CarbonImmutable::create($gy, $gm, $gd)->startOfDay();
    }

    public static function formatJalali(int $jy, int $jm, int $jd): string
    {
        return sprintf('%04d/%02d/%02d', $jy, $jm, $jd);
    }

    /** @return array{0:CarbonImmutable,1:CarbonImmutable} Gregorian bounds of a Jalali quarter */
    public static function quarterBounds(int $jalaliYear, int $quarter): array
    {
        $startMonth = ($quarter - 1) * 3 + 1;
        $endMonth = $startMonth + 2;
        $endDay = CalendarUtils::jalaliMonthLength($jalaliYear, $endMonth);

        return [
            self::jalaliToDate(self::formatJalali($jalaliYear, $startMonth, 1)),
            self::jalaliToDate(self::formatJalali($jalaliYear, $endMonth, $endDay)),
        ];
    }

    public const MONTHS = [1 => 'فروردین', 2 => 'اردیبهشت', 3 => 'خرداد', 4 => 'تیر', 5 => 'مرداد', 6 => 'شهریور',
        7 => 'مهر', 8 => 'آبان', 9 => 'آذر', 10 => 'دی', 11 => 'بهمن', 12 => 'اسفند'];

    /** @return array{0:int,1:int} current Jalali [year, month] */
    public static function currentYearMonth(): array
    {
        [$jy, $jm] = CalendarUtils::toJalali((int) date('Y'), (int) date('n'), (int) date('j'));

        return [$jy, $jm];
    }

    /** Parse «1405-07» / «1405/7» into [year, month], falling back to the current month. */
    public static function yearMonth(?string $value): array
    {
        if ($value && preg_match('#^(\d{4})[/-](\d{1,2})$#', self::normalize($value), $m) && (int) $m[2] >= 1 && (int) $m[2] <= 12) {
            return [(int) $m[1], (int) $m[2]];
        }

        return self::currentYearMonth();
    }

    public static function monthLabel(int $jy, int $jm): string
    {
        return self::MONTHS[$jm].' '.$jy;
    }
}
