<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\FiscalPeriod;
use App\Models\Target;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Commission\CalculationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class TargetController extends Controller
{
    private const FIELDS = ['annual_target', 'quarter_target', 'carried_over_override', 'lost_sales', 'prior_receivables', 'notes'];

    public function __construct(private AuditLogger $audit) {}

    public function index(Request $request, CalculationService $calc): Response
    {
        $periods = FiscalPeriod::query()->orderByDesc('starts_on')->get();
        $period = $periods->firstWhere('id', $request->integer('period')) ?? $periods->first(fn ($p) => $p->starts_on->isPast() && $p->ends_on->isFuture()) ?? $periods->first();
        $users = User::where('tenant_id', $request->user()->tenant_id)->where('is_active', true)
            ->whereIn('role', [Role::SalesExpert->value, Role::SalesHead->value])->with('team')->orderBy('role')->orderBy('name')->get();
        $targets = $period ? Target::where('fiscal_period_id', $period->id)->get()->keyBy('user_id') : collect();
        $shares = $period ? ($calc->ruleVersionFor($period)->parameters['targets']['quarter_shares'] ?? []) : [];

        return Inertia::render('Targets/Index', [
            'periods' => $periods->map(fn ($p) => ['id' => $p->id, 'label' => $p->label(), 'status' => $p->status])->values(),
            'periodId' => $period?->id,
            'quarterShare' => $period ? ($shares[(string) $period->quarter] ?? null) : null,
            'locked' => $period?->isLocked() ?? true,
            'rows' => $users->map(function (User $u) use ($targets, $period, $calc) {
                $t = $targets->get($u->id);

                return [
                    'userId' => $u->id,
                    'name' => $u->name,
                    'role' => $u->role->value,
                    'roleLabel' => $u->role->label(),
                    'team' => $u->team?->name,
                    'annual_target' => (string) ($t->annual_target ?? '0'),
                    'quarter_target' => (string) ($t->quarter_target ?? '0'),
                    'carried_over_override' => $t?->carried_over_override !== null ? (string) $t->carried_over_override : '',
                    'carried_over_auto' => $period ? $calc->carriedOver($u, $period, null) : '0',
                    'lost_sales' => (string) ($t->lost_sales ?? '0'),
                    'prior_receivables' => (string) ($t->prior_receivables ?? '0'),
                    'notes' => $t->notes ?? '',
                ];
            })->values(),
        ]);
    }

    public function update(Request $request, FiscalPeriod $period, User $user): RedirectResponse
    {
        abort_unless($user->tenant_id === $request->user()->tenant_id, 404);
        if ($period->isLocked()) {
            return back()->with('error', 'دوره قفل است و تارگت قابل تغییر نیست.');
        }
        $money = ['nullable', 'regex:/^\d{1,18}$/'];
        $data = $request->validate([
            'annual_target' => $money, 'quarter_target' => $money, 'carried_over_override' => $money,
            'lost_sales' => $money, 'prior_receivables' => $money, 'notes' => ['nullable', 'string', 'max:255'],
        ], ['regex' => ':attribute باید عدد صحیح ریالی (بدون اعشار و جداکننده) باشد.']);
        foreach (['annual_target', 'quarter_target', 'lost_sales', 'prior_receivables'] as $k) {
            $data[$k] = ($data[$k] ?? null) === null ? '0' : $data[$k];
        }

        DB::transaction(function () use ($period, $user, $data) {
            $target = Target::firstOrNew(['user_id' => $user->id, 'fiscal_period_id' => $period->id], ['tenant_id' => $user->tenant_id]);
            $before = $target->exists ? $target->only(self::FIELDS) : null;
            $target->fill($data)->save();
            $this->audit->log('target.saved', $target, $before, $target->only(self::FIELDS), "{$user->name} — {$period->label()}");
        });

        return back()->with('success', "تارگت {$user->name} ذخیره شد.");
    }
}
