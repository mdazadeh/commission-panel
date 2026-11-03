<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    public const CATEGORIES = [
        'rep_contract' => 'نماینده با قرارداد (۱٪ خالص)',
        'rep_no_contract' => 'نماینده / واسطه بدون قرارداد',
        'end_user' => 'مصرف‌کننده نهایی',
    ];

    public function index(Request $request): Response
    {
        $q = trim((string) $request->query('q', ''));
        $customers = Customer::query()
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('code', 'like', "%{$q}%")->orWhere('name', 'like', "%{$q}%")))
            ->when($request->query('category'), fn ($w, $c) => $w->where('category', $c))
            ->orderBy('name')->paginate(50)->withQueryString()
            ->through(fn (Customer $c) => ['id' => $c->id, 'code' => $c->code, 'name' => $c->name, 'category' => $c->category]);

        return Inertia::render('Customers/Index', [
            'customers' => $customers,
            'q' => $q,
            'category' => $request->query('category'),
            'categories' => collect(self::CATEGORIES)->map(fn ($label, $value) => compact('value', 'label'))->values(),
        ]);
    }

    public function update(Request $request, Customer $customer, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate(['category' => ['nullable', Rule::in(array_keys(self::CATEGORIES))]]);
        DB::transaction(function () use ($customer, $data, $audit) {
            $before = $customer->only(['category']);
            $customer->update(['category' => $data['category'] ?? null]);
            $audit->log('customer.category_changed', $customer, $before, $customer->only(['category']), $customer->code);
        });

        return back()->with('success', "نوع مشتری {$customer->name} ذخیره شد.");
    }
}
