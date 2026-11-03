<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CalculationLine extends Model
{
    public $timestamps = false;

    protected $fillable = ['calculation_run_id', 'user_id', 'sales_document_id', 'category', 'net_amount', 'cash_amount', 'credit_amount', 'rate', 'commission', 'note'];

    public function document(): BelongsTo
    {
        return $this->belongsTo(SalesDocument::class, 'sales_document_id');
    }
}
