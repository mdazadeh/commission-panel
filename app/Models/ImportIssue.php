<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImportIssue extends Model
{
    public $timestamps = false;

    protected $fillable = ['import_batch_id', 'row_number', 'severity', 'code', 'message', 'raw'];

    protected function casts(): array
    {
        return ['raw' => 'array'];
    }
}
