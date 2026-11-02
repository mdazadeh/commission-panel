<?php

namespace App\Services;

use App\Models\FiscalPeriod;
use App\Support\Persian;
use Carbon\CarbonInterface;

class FiscalCalendar
{
    /** Create the four Jalali quarters of a year (idempotent). */
    public function ensureYear(int $tenantId, int $jalaliYear): void
    {
        foreach ([1, 2, 3, 4] as $q) {
            [$start, $end] = Persian::quarterBounds($jalaliYear, $q);
            FiscalPeriod::withoutGlobalScopes()->firstOrCreate(
                ['tenant_id' => $tenantId, 'jalali_year' => $jalaliYear, 'quarter' => $q],
                ['starts_on' => $start->toDateString(), 'ends_on' => $end->toDateString(), 'status' => 'open'],
            );
        }
    }

    public function periodFor(int $tenantId, CarbonInterface $date): ?FiscalPeriod
    {
        return FiscalPeriod::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereDate('starts_on', '<=', $date)
            ->whereDate('ends_on', '>=', $date)
            ->first();
    }
}
