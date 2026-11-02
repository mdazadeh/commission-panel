<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class FiscalPeriod extends Model
{
    use BelongsToTenant;

    public const QUARTER_NAMES = [1 => 'بهار', 2 => 'تابستان', 3 => 'پاییز', 4 => 'زمستان'];

    protected $fillable = ['tenant_id', 'jalali_year', 'quarter', 'starts_on', 'ends_on', 'status', 'locked_at', 'locked_by'];

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date', 'locked_at' => 'datetime'];
    }

    public function label(): string
    {
        return self::QUARTER_NAMES[$this->quarter].' '.$this->jalali_year;
    }

    public function isLocked(): bool
    {
        return $this->status !== 'open';
    }
}
