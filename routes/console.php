<?php

use App\Services\AuditLogger;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

/*
 * Shared hosting: proc_open/exec are disabled, so every scheduled task must run in-process
 * (Schedule::call / Schedule::job), never Schedule::command / ->exec().
 */
Schedule::call(fn () => Cache::forever('scheduler.heartbeat', now()->toIso8601String()))
    ->name('heartbeat')->everyMinute();

Artisan::command('audit:verify', function (AuditLogger $audit) {
    $r = $audit->verify();
    $this->line($r['ok'] ? "OK — {$r['checked']} events verified" : "BROKEN at event #{$r['broken_at']}");

    return $r['ok'] ? 0 : 1;
})->purpose('Verify the integrity of the audit hash chain');
