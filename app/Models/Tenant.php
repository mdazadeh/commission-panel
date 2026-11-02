<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tenant extends Model
{
    protected $fillable = ['code', 'name', 'settings'];

    protected function casts(): array
    {
        return ['settings' => 'array'];
    }
}
