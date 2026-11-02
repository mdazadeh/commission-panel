<?php

namespace Tests\Unit;

use App\Support\Persian;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PersianTest extends TestCase
{
    public function test_normalizes_arabic_letters_and_digits(): void
    {
        $this->assertSame('کد خریدار', Persian::normalize(' كد  خريدار '));
        $this->assertSame('1405/01/24', Persian::normalize('۱۴۰۵/۰۱/۲۴'));
        $this->assertSame('شمارهسریالفاکتورفروش', Persian::key('شماره سريال فاکتور فروش'));
    }

    #[DataProvider('amounts')]
    public function test_parses_amounts_to_integer_rial(mixed $in, string $out): void
    {
        $this->assertSame($out, Persian::toRial($in));
    }

    public static function amounts(): array
    {
        return [
            'farapayam float noise' => [112035714999.99998, '112035715000'],
            'int' => [78000000000, '78000000000'],
            'negative return' => [-930590000, '-930590000'],
            'persian digits with separators' => ['۱٬۲۵۰٬۰۰۰', '1250000'],
            'comma string' => ['1,000,000', '1000000'],
            'empty' => [null, '0'],
        ];
    }

    public function test_rejects_garbage_amount(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Persian::toRial('حدود ۴ میلیارد');
    }

    public function test_converts_jalali_dates(): void
    {
        $this->assertSame('2026-03-21', Persian::jalaliToDate('1405/01/01')->toDateString());
        $this->assertSame('2026-04-13', Persian::jalaliToDate('1405/01/24')->toDateString());
    }

    public function test_rejects_invalid_jalali_date(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Persian::jalaliToDate('1405/12/30'); // 1405 is not a leap year
    }

    public function test_quarter_bounds(): void
    {
        [$s, $e] = Persian::quarterBounds(1405, 1);
        $this->assertSame(['2026-03-21', '2026-06-21'], [$s->toDateString(), $e->toDateString()]);
        [, $e4] = Persian::quarterBounds(1405, 4);
        $this->assertSame(Persian::jalaliToDate('1405/12/29')->toDateString(), $e4->toDateString());
    }
}
