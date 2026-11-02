<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class RuleVersion extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'code', 'title', 'effective_from', 'effective_to', 'status', 'parameters', 'notes', 'approved_by', 'approved_at'];

    protected function casts(): array
    {
        return ['parameters' => 'array', 'effective_from' => 'date', 'effective_to' => 'date', 'approved_at' => 'datetime'];
    }
}
