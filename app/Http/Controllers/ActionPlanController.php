<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ReviewsTeamItems;
use App\Models\ActionPlanItem;
use App\Models\Customer;
use App\Services\AuditLogger;
use App\Support\Persian;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Monthly action plan (D-05): a sale counts as «action plan» only if the customer is in the
 * salesperson's own APPROVED plan for the month of the invoice.
 */
class ActionPlanController extends Controller
{
    use ReviewsTeamItems;

    public function __construct(private AuditLogger $audit) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        [$jy, $jm] = Persian::yearMonth($request->query('month'));

        $map = fn (ActionPlanItem $i) => [
            'id' => $i->id,
            'user' => $i->user?->name,
            'customerCode' => $i->customer_code,
            'customerName' => $i->customer_name,
            'leadStatus' => $i->lead_status,
            'segment' => $i->segment,
            'productForecast' => $i->product_forecast,
            'amountForecast' => $i->amount_forecast !== null ? (string) $i->amount_forecast : null,
            'notes' => $i->notes,
            'status' => $i->status,
            'reviewNote' => $i->review_note,
            'reviewer' => $i->reviewer?->name,
        ];

        $own = $user->hasPermission('action_plan.submit')
            ? ActionPlanItem::with('reviewer:id,name')->where(['user_id' => $user->id, 'jalali_year' => $jy, 'jalali_month' => $jm])->orderBy('id')->get()->map($map)
            : collect();

        $reviewIds = $this->reviewableUserIds($user, 'action_plan.review');
        $review = $reviewIds === [] ? collect() : ActionPlanItem::with(['user:id,name', 'reviewer:id,name'])
            ->where(['jalali_year' => $jy, 'jalali_month' => $jm])
            ->where('user_id', '!=', $user->id)
            ->when($reviewIds !== null, fn ($q) => $q->whereIn('user_id', $reviewIds))
            ->orderByRaw("CASE WHEN status = 'submitted' THEN 0 ELSE 1 END")->orderBy('user_id')->get()->map($map);

        return Inertia::render('ActionPlans/Index', [
            'month' => sprintf('%04d-%02d', $jy, $jm),
            'monthLabel' => Persian::monthLabel($jy, $jm),
            'own' => $own->values(),
            'review' => $review->values(),
            'canSubmit' => $user->hasPermission('action_plan.submit'),
            'canReview' => $reviewIds !== [],
            'leadStatuses' => collect(ActionPlanItem::LEAD_STATUSES)->map(fn ($label, $value) => compact('value', 'label'))->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'month' => ['required', 'regex:/^\d{4}-\d{2}$/'],
            'customer_code' => ['required', 'string', 'max:32'],
            'customer_name' => ['nullable', 'string', 'max:190'],
            'lead_status' => ['required', Rule::in(array_keys(ActionPlanItem::LEAD_STATUSES))],
            'segment' => ['nullable', 'string', 'max:190'],
            'product_forecast' => ['nullable', 'string', 'max:190'],
            'amount_forecast' => ['nullable', 'regex:/^\d{1,18}$/'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], ['amount_forecast.regex' => 'مبلغ پیش‌بینی باید عدد صحیح ریالی باشد.']);
        [$jy, $jm] = Persian::yearMonth($data['month']);
        $code = Persian::normalize($data['customer_code']);
        $name = Persian::normalize($data['customer_name'] ?? '') ?: (Customer::where('code', $code)->value('name') ?? '');
        if ($name === '') {
            return back()->withErrors(['customer_name' => 'این کد در فهرست مشتریان نیست؛ نام مشتری را وارد کنید.']);
        }
        if (ActionPlanItem::where(['user_id' => $user->id, 'jalali_year' => $jy, 'jalali_month' => $jm, 'customer_code' => $code])->exists()) {
            return back()->withErrors(['customer_code' => 'این مشتری در اکشن‌پلن همین ماه شما ثبت شده است.']);
        }

        DB::transaction(function () use ($user, $jy, $jm, $code, $name, $data) {
            $item = ActionPlanItem::create([
                'tenant_id' => $user->tenant_id, 'user_id' => $user->id, 'jalali_year' => $jy, 'jalali_month' => $jm,
                'customer_code' => $code, 'customer_name' => $name, 'lead_status' => $data['lead_status'],
                'segment' => $data['segment'] ?? null, 'product_forecast' => $data['product_forecast'] ?? null,
                'amount_forecast' => $data['amount_forecast'] ?? null, 'notes' => $data['notes'] ?? null, 'status' => 'submitted',
            ]);
            $this->audit->log('action_plan.submitted', $item, null, $item->only(['jalali_year', 'jalali_month', 'customer_code', 'lead_status']));
        });

        return back()->with('success', 'مشتری به اکشن‌پلن اضافه شد و منتظر تأیید است.');
    }

    public function destroy(Request $request, ActionPlanItem $item): RedirectResponse
    {
        abort_unless($item->user_id === $request->user()->id, 403);
        if ($item->status === 'approved') {
            return back()->with('error', 'ردیف تأییدشده قابل حذف نیست.');
        }
        DB::transaction(function () use ($item) {
            $this->audit->log('action_plan.deleted', $item, $item->only(['jalali_year', 'jalali_month', 'customer_code', 'status']), null);
            $item->delete();
        });

        return back()->with('success', 'ردیف حذف شد.');
    }

    public function review(Request $request, ActionPlanItem $item): RedirectResponse
    {
        abort_unless($this->canReview($request->user(), $item->user_id, 'action_plan.review'), 403);
        $data = $request->validate([
            'decision' => ['required', Rule::in(['approved', 'rejected'])],
            'note' => ['nullable', 'string', 'max:255', 'required_if:decision,rejected'],
        ], ['note.required_if' => 'برای رد کردن، دلیل را بنویسید.']);

        DB::transaction(function () use ($request, $item, $data) {
            $before = $item->only(['status', 'review_note']);
            $item->update(['status' => $data['decision'], 'review_note' => $data['note'] ?? null, 'reviewed_by' => $request->user()->id, 'reviewed_at' => Carbon::now()]);
            $this->audit->log("action_plan.{$data['decision']}", $item, $before, $item->only(['status', 'review_note']), $data['note'] ?? null);
        });

        return back()->with('success', $data['decision'] === 'approved' ? 'تأیید شد.' : 'رد شد.');
    }
}
