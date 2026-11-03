<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityEntry extends Model
{
    use BelongsToTenant;

    public const INDICATORS = [
        'customer_meetings' => 'جلسات با مشتری',
        'action_plan' => 'فعالیت اکشن‌پلن',
        'internal_meetings' => 'جلسات درون‌سازمانی',
    ];

    protected $fillable = [
        'tenant_id', 'user_id', 'jalali_year', 'jalali_month', 'indicator', 'target', 'actual', 'evidence',
        'status', 'reviewed_by', 'reviewed_at', 'review_note',
    ];

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime', 'target' => 'integer', 'actual' => 'integer'];
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
