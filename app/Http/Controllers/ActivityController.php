<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ReviewsTeamItems;
use App\Models\ActionPlanItem;
use App\Models\ActivityEntry;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\Persian;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** D-12: the salesperson records monthly activity; the head (or a manager) approves it. Unapproved entries never count (AT-04). */
class ActivityController extends Controller
{
    use ReviewsTeamItems;

    public function __construct(private AuditLogger $audit) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        [$jy, $jm] = Persian::yearMonth($request->query('month'));

        $entry = fn (ActivityEntry $e) => [
            'id' => $e->id, 'user' => $e->user?->name, 'indicator' => $e->indicator, 'target' => $e->target, 'actual' => $e->actual,
            'evidence' => $e->evidence, 'status' => $e->status, 'reviewNote' => $e->review_note, 'reviewer' => $e->reviewer?->name,
        ];

        $own = ActivityEntry::with('reviewer:id,name')->where(['user_id' => $user->id, 'jalali_year' => $jy, 'jalali_month' => $jm])->get()->keyBy('indicator');
        $ownRows = collect(ActivityEntry::INDICATORS)->map(fn ($label, $ind) => $own->has($ind)
            ? ['label' => $label] + $entry($own[$ind])
            : ['label' => $label, 'id' => null, 'indicator' => $ind, 'target' => $this->defaultTarget($user, $ind, $jy, $jm), 'actual' => null, 'evidence' => null, 'status' => 'new', 'reviewNote' => null, 'reviewer' => null])->values();

        $reviewIds = $this->reviewableUserIds($user, 'activity.review');
        $review = $reviewIds === [] ? collect() : ActivityEntry::with(['user:id,name', 'reviewer:id,name'])
            ->where(['jalali_year' => $jy, 'jalali_month' => $jm])->where('user_id', '!=', $user->id)
            ->when($reviewIds !== null, fn ($q) => $q->whereIn('user_id', $reviewIds))
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")->orderBy('user_id')->get()
            ->map(fn ($e) => ['label' => ActivityEntry::INDICATORS[$e->indicator] ?? $e->indicator] + $entry($e));

        return Inertia::render('Activity/Index', [
            'month' => sprintf('%04d-%02d', $jy, $jm),
            'monthLabel' => Persian::monthLabel($jy, $jm),
            'own' => $user->hasPermission('activity.submit') ? $ownRows : [],
            'review' => $review->values(),
            'canSubmit' => $user->hasPermission('activity.submit'),
            'canReview' => $reviewIds !== [],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'month' => ['required', 'regex:/^\d{4}-\d{2}$/'],
            'indicator' => ['required', Rule::in(array_keys(ActivityEntry::INDICATORS))],
            'target' => ['required', 'integer', 'min:0', 'max:1000'],
            'actual' => ['required', 'integer', 'min:0', 'max:1000'],
            'evidence' => ['nullable', 'string', 'max:1000'],
        ]);
        [$jy, $jm] = Persian::yearMonth($data['month']);

        $existing = ActivityEntry::where(['user_id' => $user->id, 'jalali_year' => $jy, 'jalali_month' => $jm, 'indicator' => $data['indicator']])->first();
        if ($existing?->status === 'approved') {
            return back()->with('error', 'این مورد تأیید شده و قابل ویرایش نیست.');
        }

        DB::transaction(function () use ($user, $jy, $jm, $data, $existing) {
            $before = $existing?->only(['target', 'actual', 'status']);
            $entry = ActivityEntry::updateOrCreate(
                ['user_id' => $user->id, 'jalali_year' => $jy, 'jalali_month' => $jm, 'indicator' => $data['indicator']],
                ['tenant_id' => $user->tenant_id, 'target' => $data['target'], 'actual' => $data['actual'], 'evidence' => $data['evidence'] ?? null,
                    'status' => 'pending', 'reviewed_by' => null, 'reviewed_at' => null, 'review_note' => null],
            );
            $this->audit->log('activity.submitted', $entry, $before, $entry->only(['target', 'actual', 'status']));
        });

        return back()->with('success', 'ثبت شد و منتظر تأیید است.');
    }

    public function review(Request $request, ActivityEntry $entry): RedirectResponse
    {
        abort_unless($this->canReview($request->user(), $entry->user_id, 'activity.review'), 403);
        $data = $request->validate([
            'decision' => ['required', Rule::in(['approved', 'rejected'])],
            'note' => ['nullable', 'string', 'max:255', 'required_if:decision,rejected'],
        ], ['note.required_if' => 'برای رد کردن، دلیل را بنویسید.']);

        DB::transaction(function () use ($request, $entry, $data) {
            $before = $entry->only(['status', 'review_note']);
            $entry->update(['status' => $data['decision'], 'review_note' => $data['note'] ?? null, 'reviewed_by' => $request->user()->id, 'reviewed_at' => Carbon::now()]);
            $this->audit->log("activity.{$data['decision']}", $entry, $before, $entry->only(['status', 'review_note']), $data['note'] ?? null);
        });

        return back()->with('success', $data['decision'] === 'approved' ? 'تأیید شد.' : 'رد شد.');
    }

    private function defaultTarget(User $user, string $indicator, int $jy, int $jm): int
    {
        return match ($indicator) {
            // Defaults mirror activity.default_targets in database/data/rule-version-1405-v1.json; editable per entry.
            'customer_meetings' => 12,
            'internal_meetings' => 4,
            'action_plan' => ActionPlanItem::where(['user_id' => $user->id, 'jalali_year' => $jy, 'jalali_month' => $jm])->where('status', '!=', 'rejected')->count(),
        };
    }
}
