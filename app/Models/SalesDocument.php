<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesDocument extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'import_batch_id', 'doc_type', 'serial', 'line_no', 'issued_on', 'issued_on_jalali',
        'fiscal_period_id', 'period_override_reason', 'customer_code', 'customer_id', 'marketer_code',
        'user_id', 'department', 'product_code', 'quantity', 'gross_amount', 'discount_amount',
        'net_amount', 'cash_amount', 'credit_amount', 'row_hash',
    ];

    /*
     * issued_on is deliberately kept as a plain 'Y-m-d' string (no date cast): a date cast would
     * store 'Y-m-d H:i:s' on SQLite and break exact natural-key lookups. MySQL DATE returns 'Y-m-d'.
     */

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(FiscalPeriod::class, 'fiscal_period_id');
    }
}
