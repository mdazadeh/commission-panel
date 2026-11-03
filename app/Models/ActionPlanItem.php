<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActionPlanItem extends Model
{
    use BelongsToTenant;

    public const LEAD_STATUSES = ['cold' => 'سرد', 'warm' => 'گرم', 'hot' => 'داغ', 'current' => 'جاری'];

    protected $fillable = [
        'tenant_id', 'user_id', 'jalali_year', 'jalali_month', 'customer_code', 'customer_name', 'lead_status',
        'segment', 'product_forecast', 'amount_forecast', 'notes', 'status', 'reviewed_by', 'reviewed_at', 'review_note',
    ];

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
