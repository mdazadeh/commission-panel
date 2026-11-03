<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CalculationResult extends Model
{
    protected $fillable = [
        'calculation_run_id', 'user_id', 'role', 'sales_total', 'target_total', 'achievement', 'tier_rate',
        'final_amount', 'payable_amount', 'components', 'flags',
    ];

    protected function casts(): array
    {
        return ['components' => 'array', 'flags' => 'array'];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(CalculationRun::class, 'calculation_run_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
