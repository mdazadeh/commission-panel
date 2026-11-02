<?php

namespace App\Http\Controllers;

use App\Models\FiscalPeriod;
use App\Models\ImportBatch;
use App\Models\SalesDocument;
use App\Models\User;
use App\Services\DataScope;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Phase-1 dashboard: verified sales figures per period, scoped by role.
 * Commission figures arrive with the calculation engine (phase 2); nothing here is a payable amount.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request, DataScope $scope): Response
    {
        $user = $request->user();
        $level = $scope->level($user);

        $periods = FiscalPeriod::query()->orderByDesc('starts_on')->get();
        $current = $periods->firstWhere(fn ($p) => $p->starts_on->lte(Carbon::today()) && $p->ends_on->gte(Carbon::today()));
        $period = $request->integer('period') ? $periods->firstWhere('id', $request->integer('period')) : $current;

        $base = SalesDocument::query()->when($period, fn ($q) => $q->where('fiscal_period_id', $period->id));
        $scope->apply($base, $user);

        $totals = $level === 'none' ? null : (clone $base)->selectRaw(
            'COUNT(*) as documents, COALESCE(SUM(net_amount),0) as net, COALESCE(SUM(discount_amount),0) as discount, COALESCE(SUM(credit_amount),0) as credit'
        )->first();

        $names = User::where('tenant_id', $user->tenant_id)->pluck('name', 'id');
        $byPerson = in_array($level, ['team', 'all'], true)
            ? (clone $base)->select('user_id', DB::raw('COUNT(*) as documents'), DB::raw('SUM(net_amount) as net'))
                ->groupBy('user_id')->orderByDesc('net')->get()
                ->map(fn ($r) => [
                    'user' => $r->user_id ? ($names[$r->user_id] ?? '—') : 'بدون انتساب',
                    'documents' => (int) $r->documents,
                    'net' => (string) $r->net,
                ])
            : [];

        $lastImport = $user->hasPermission('imports.view') || $level !== 'none'
            ? ImportBatch::query()->where('status', 'committed')->latest('committed_at')->value('committed_at')
            : null;

        return Inertia::render('Dashboard', [
            'scope' => $level,
            'periods' => $periods->map(fn ($p) => ['id' => $p->id, 'label' => $p->label(), 'status' => $p->status])->values(),
            'periodId' => $period?->id,
            'totals' => $totals ? [
                'documents' => (int) $totals->documents,
                'net' => (string) $totals->net,
                'discount' => (string) $totals->discount,
                'credit' => (string) $totals->credit,
            ] : null,
            'byPerson' => $byPerson,
            'lastImportAt' => $lastImport?->toIso8601String(),
        ]);
    }
}
