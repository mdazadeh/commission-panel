<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImportBatch extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'type', 'original_filename', 'stored_path', 'file_hash', 'status',
        'total_rows', 'valid_rows', 'error_rows', 'duplicate_rows', 'inserted_rows',
        'meta', 'user_id', 'committed_at',
    ];

    protected function casts(): array
    {
        return ['meta' => 'array', 'committed_at' => 'datetime'];
    }

    public function issues(): HasMany
    {
        return $this->hasMany(ImportIssue::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
