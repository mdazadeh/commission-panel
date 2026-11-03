<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Target extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'user_id', 'fiscal_period_id', 'annual_target', 'quarter_target', 'carried_over_override', 'lost_sales', 'prior_receivables', 'notes'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(FiscalPeriod::class, 'fiscal_period_id');
    }
}
