<?php

namespace App\Http\Controllers;

use App\Domain\Commission\Money;
use App\Models\CalculationLine;
use App\Models\CalculationResult;
use App\Models\CalculationRun;
use App\Models\FiscalPeriod;
use App\Models\SalesDocument;
use App\Models\User;
use App\Services\Commission\CalculationService;
use App\Services\DataScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class CalculationController extends Controller
{
    public function index(Request $request, DataScope $scope): Response
    {
        $user = $request->user();
        $periods = FiscalPeriod::query()->orderByDesc('starts_on')->get();
        $period = $periods->firstWhere('id', $request->integer('period'))
            ?? $periods->firstWhere('id', CalculationRun::latest('id')->value('fiscal_period_id'))
            ?? $periods->first(fn ($p) => $p->starts_on->isPast() && $p->ends_on->isFuture()) ?? $periods->first();
        $run = $period ? CalculationRun::with(['creator:id,name', 'ruleVersion:id,code,status'])->where('fiscal_period_id', $period->id)->latest('id')->first() : null;

        $results = collect();
        if ($run) {
            $ids = $scope->visibleUserIds($user);
            $names = User::where('tenant_id', $user->tenant_id)->pluck('name', 'id');
            $results = $run->results()->when($ids !== null, fn ($q) => $q->whereIn('user_id', $ids))
                ->orderByDesc('payable_amount')->get()
                ->map(fn (CalculationResult $r) => self::resultRow($r, $names[$r->user_id] ?? '—'));
        }

        return Inertia::render('Calculations/Index', [
            'periods' => $periods->map(fn ($p) => ['id' => $p->id, 'label' => $p->label(), 'status' => $p->status])->values(),
            'periodId' => $period?->id,
            'run' => $run ? [
                'id' => $run->id,
                'status' => $run->status,
                'createdAt' => $run->created_at->toIso8601String(),
                'creator' => $run->creator?->name,
                'rule' => $run->ruleVersion?->code,
                'ruleStatus' => $run->ruleVersion?->status,
                'summary' => $user->hasPermission('dashboard.all') ? $run->summary : null,
            ] : null,
            'results' => $results->values(),
            'canRun' => $user->hasPermission('calc.run') && $period && ! $period->isLocked(),
        ]);
    }

    public function store(Request $request, CalculationService $service): RedirectResponse
    {
        $period = FiscalPeriod::findOrFail($request->integer('period'));
        try {
            $run = $service->run($period, $request->user());
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('calculations.index', ['period' => $period->id])
            ->with('success', "محاسبه موقت انجام شد ({$run->summary['people']} نفر).");
    }

    public function show(Request $request, CalculationResult $result, DataScope $scope): Response
    {
        $run = CalculationRun::with(['period', 'ruleVersion:id,code,status'])->findOrFail($result->calculation_run_id); // tenant-scoped
        $ids = $scope->visibleUserIds($request->user());
        abort_unless($ids === null || in_array($result->user_id, $ids, true), 404); // AT-08

        $docs = SalesDocument::withoutGlobalScopes()->whereIn('id', CalculationLine::where(['calculation_run_id' => $run->id, 'user_id' => $result->user_id])->pluck('sales_document_id'))
            ->get()->keyBy('id');
        $lines = CalculationLine::where(['calculation_run_id' => $run->id, 'user_id' => $result->user_id])->orderBy('id')->get()
            ->map(fn (CalculationLine $l) => [
                'serial' => $docs[$l->sales_document_id]->serial ?? '—',
                'date' => $docs[$l->sales_document_id]->issued_on_jalali ?? '',
                'customer' => $docs[$l->sales_document_id]->customer_code ?? '',
                'type' => $docs[$l->sales_document_id]->doc_type ?? '',
                'category' => $l->category,
                'net' => (string) $l->net_amount,
                'cash' => (string) $l->cash_amount,
                'credit' => (string) $l->credit_amount,
                'rate' => (string) $l->rate,
                'commission' => (string) $l->commission,
            ]);

        return Inertia::render('Calculations/Show', [
            'result' => self::resultRow($result, User::find($result->user_id)?->name ?? '—') + [
                'components' => array_values(array_map(fn ($code, $c) => ['code' => $code] + $c, array_keys($result->components['items']), $result->components['items'])),
            ],
            'run' => ['id' => $run->id, 'status' => $run->status, 'period' => $run->period->label(), 'periodId' => $run->fiscal_period_id,
                'rule' => $run->ruleVersion?->code, 'ruleStatus' => $run->ruleVersion?->status, 'createdAt' => $run->created_at->toIso8601String()],
            'lines' => $lines,
        ]);
    }

    public static function resultRow(CalculationResult $r, string $name): array
    {
        return [
            'id' => $r->id,
            'userId' => $r->user_id,
            'name' => $name,
            'role' => $r->role,
            'salesTotal' => (string) $r->sales_total,
            'targetTotal' => (string) $r->target_total,
            // Normalised scales: identical output on MySQL ('0.900000') and SQLite ('0.9').
            'achievement' => (string) Money::of((string) $r->achievement)->toScale(6),
            'tierRate' => (string) Money::of((string) $r->tier_rate)->toScale(5),
            'final' => Money::str(Money::of((string) $r->final_amount)),
            'payable' => (string) $r->payable_amount,
            'nextTier' => $r->components['next_tier'] ?? null,
            'activityRate' => $r->components['items']['activity_rate']['value'] ?? '0',
            'flags' => $r->flags,
        ];
    }
}
