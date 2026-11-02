<?php

namespace App\Http\Controllers;

use App\Services\AuditLogger;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/** System status and «update without SSH»: runs pending migrations from the browser. */
class SystemController extends Controller
{
    public function index(Migrator $migrator): Response
    {
        return Inertia::render('System/Index', [
            'status' => [
                'appVersion' => config('app.version'),
                'php' => PHP_VERSION,
                'database' => $this->databaseVersion(),
                'pendingMigrations' => $this->pending($migrator),
                'schedulerHeartbeat' => Cache::get('scheduler.heartbeat'),
                'queuePending' => DB::table('jobs')->count(),
                'queueFailed' => DB::table('failed_jobs')->count(),
                'cronCommand' => $this->cronCommand(),
            ],
        ]);
    }

    public function migrate(AuditLogger $audit): RedirectResponse
    {
        try {
            Artisan::call('migrate', ['--force' => true]);
            $output = trim(Artisan::output());
            Artisan::call('optimize:clear');
        } catch (Throwable $e) {
            $audit->log('system.migrate_failed', null, null, ['error' => $e->getMessage()]);

            return back()->with('error', 'به‌روزرسانی پایگاه داده ناموفق بود: '.$e->getMessage());
        }
        $audit->log('system.migrated', null, null, ['output' => mb_substr($output, 0, 2000)]);

        return back()->with('success', 'پایگاه داده به‌روز شد.');
    }

    /** @return list<string> */
    private function pending(Migrator $migrator): array
    {
        if (! $migrator->repositoryExists()) {
            return ['(migration table missing)'];
        }
        $files = $migrator->getMigrationFiles([database_path('migrations')]);
        $ran = $migrator->getRepository()->getRan();

        return array_values(array_diff(array_keys($files), $ran));
    }

    private function databaseVersion(): string
    {
        try {
            return DB::connection()->getDriverName().' '.DB::selectOne(
                DB::connection()->getDriverName() === 'sqlite' ? 'select sqlite_version() as v' : 'select version() as v'
            )->v;
        } catch (Throwable) {
            return 'unknown';
        }
    }

    private function cronCommand(): string
    {
        return '* * * * * /usr/local/bin/php '.base_path('artisan').' schedule:run >> /dev/null 2>&1';
    }
}
