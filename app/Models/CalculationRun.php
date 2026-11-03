<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CalculationRun extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'fiscal_period_id', 'rule_version_id', 'status', 'inputs_hash', 'summary', 'created_by'];

    protected function casts(): array
    {
        return ['summary' => 'array'];
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(FiscalPeriod::class, 'fiscal_period_id');
    }

    public function ruleVersion(): BelongsTo
    {
        return $this->belongsTo(RuleVersion::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function results(): HasMany
    {
        return $this->hasMany(CalculationResult::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(CalculationLine::class);
    }
}
