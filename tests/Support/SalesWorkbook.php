<?php

namespace Tests\Support;

use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

/** Builds .xlsx files shaped like the real Farapayam «ریز فروش» export (Arabic ي/ك headers, blank rows, derived columns). */
final class SalesWorkbook
{
    public const HEADERS = [
        'نوع فروش', 'شماره سريال فاکتور فروش', 'تاريخ فاکتور', 'ماه', 'محاسبه شده', 'كد خريدار', 'نام خريدار',
        'کد بازارياب', 'بازارياب', 'دپارتمان', 'نوع مشتری', 'ماهیت', 'مبلغ کل', 'تخفيف رديفي', 'مبلغ کل پس از تخفیف', 'نقدی', 'تعهدی',
    ];

    /** @param list<array{0:string,1:string,2:string,3:string,4:string,5:int|float,6:int|float,7:int|float,8:int|float,9:int|float}> $rows
     *  each row: [type, serial, jalaliDate, customerCode, marketerCode, gross, discount, net, cash, credit] */
    public static function make(array $rows, string $sheetName = 'ریز فروش'): string
    {
        $path = tempnam(sys_get_temp_dir(), 'sales').'.xlsx';
        $writer = new Writer;
        $writer->openToFile($path);
        $writer->getCurrentSheet()->setName($sheetName);
        $writer->addRow(Row::fromValues(self::HEADERS));
        $writer->addRow(Row::fromValues([]));
        $writer->addRow(Row::fromValues([]));
        foreach ($rows as [$type, $serial, $date, $cust, $mkt, $gross, $disc, $net, $cash, $credit]) {
            $writer->addRow(Row::fromValues([
                $type, $serial, $date, '1', 'بهار', $cust, 'مشتری '.$cust, (int) $mkt, 'کارشناس', 'دیجیتال', 'FALSE', 'نماینده بدون قرارداد و غیر لیبل',
                $gross, $disc, $net, $cash, $credit,
            ]));
        }
        $writer->close();

        return $path;
    }
}
