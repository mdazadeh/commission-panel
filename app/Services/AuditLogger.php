<?php

namespace App\Services;

use App\Models\AuditEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AuditLogger
{
    public const GENESIS = '0000000000000000000000000000000000000000000000000000000000000000';

    /** Fields never written to the audit trail. */
    private const REDACT = ['password', 'remember_token'];

    public function log(string $action, ?Model $subject = null, ?array $before = null, ?array $after = null, ?string $reason = null, ?int $tenantId = null): AuditEvent
    {
        $user = Auth::user();
        $request = app()->bound('request') ? request() : null;

        return DB::transaction(function () use ($action, $subject, $before, $after, $reason, $tenantId, $user, $request) {
            $prev = AuditEvent::query()->orderByDesc('id')->lockForUpdate()->value('hash') ?? self::GENESIS;

            $row = [
                'tenant_id' => $tenantId ?? $user?->tenant_id ?? $subject?->getAttribute('tenant_id'),
                'user_id' => $user?->id,
                'action' => $action,
                'subject_type' => $subject ? class_basename($subject) : null,
                'subject_id' => $subject?->getKey(),
                'before' => $this->clean($before),
                'after' => $this->clean($after),
                'reason' => $reason,
                'ip' => $request?->ip(),
                'user_agent' => $request ? mb_substr((string) $request->userAgent(), 0, 255) : null,
                'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
                'prev_hash' => $prev,
            ];
            $row['hash'] = self::hashOf($row);

            return AuditEvent::create($row);
        });
    }

    /** @return array{ok:bool,checked:int,broken_at:?int} */
    public function verify(): array
    {
        $prev = self::GENESIS;
        $checked = 0;
        foreach (AuditEvent::query()->orderBy('id')->lazyById(500) as $event) {
            $row = [
                'tenant_id' => $event->tenant_id,
                'user_id' => $event->user_id,
                'action' => $event->action,
                'subject_type' => $event->subject_type,
                'subject_id' => $event->subject_id,
                'before' => $event->before,
                'after' => $event->after,
                'reason' => $event->reason,
                'ip' => $event->ip,
                'user_agent' => $event->user_agent,
                'created_at' => $event->created_at->format('Y-m-d H:i:s'),
                'prev_hash' => $event->prev_hash,
            ];
            if ($event->prev_hash !== $prev || ! hash_equals($event->hash, self::hashOf($row))) {
                return ['ok' => false, 'checked' => $checked, 'broken_at' => $event->id];
            }
            $prev = $event->hash;
            $checked++;
        }

        return ['ok' => true, 'checked' => $checked, 'broken_at' => null];
    }

    public static function hashOf(array $row): string
    {
        $fields = [];
        foreach (['tenant_id', 'user_id', 'action', 'subject_type', 'subject_id', 'before', 'after', 'reason', 'ip', 'user_agent', 'created_at', 'prev_hash'] as $k) {
            $v = $row[$k] ?? null;
            $fields[$k] = is_array($v) ? self::canonical($v) : ($v === null ? null : (string) $v);
        }

        return hash('sha256', json_encode($fields, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private static function canonical(array $value): array
    {
        if (! array_is_list($value)) {
            ksort($value);
        }
        foreach ($value as $k => $v) {
            $value[$k] = is_array($v) ? self::canonical($v) : ($v === null ? null : (string) $v);
        }

        return $value;
    }

    private function clean(?array $data): ?array
    {
        if ($data === null) {
            return null;
        }
        foreach (self::REDACT as $key) {
            unset($data[$key]);
        }

        return $data;
    }
}
