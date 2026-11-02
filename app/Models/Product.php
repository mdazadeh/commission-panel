<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'code', 'name', 'brand', 'is_faran_brand', 'kind', 'kva', 'ah'];

    protected function casts(): array
    {
        return ['is_faran_brand' => 'boolean', 'kva' => 'decimal:3', 'ah' => 'decimal:3'];
    }
}
