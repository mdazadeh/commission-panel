<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

/** Append-only. Updates and deletes are refused at model level; integrity is provable via the hash chain. */
class AuditEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'tenant_id', 'user_id', 'action', 'subject_type', 'subject_id', 'before', 'after',
        'reason', 'ip', 'user_agent', 'created_at', 'prev_hash', 'hash',
    ];

    protected function casts(): array
    {
        return ['before' => 'array', 'after' => 'array', 'created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Audit events are immutable.'));
        static::deleting(fn () => throw new LogicException('Audit events are immutable.'));
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
