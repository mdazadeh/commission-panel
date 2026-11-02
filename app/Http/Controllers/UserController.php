<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\Team;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    private const AUDITED = ['username', 'name', 'email', 'role', 'team_id', 'marketer_code', 'sales_started_on', 'is_active'];

    public function __construct(private AuditLogger $audit) {}

    public function index(Request $request): Response
    {
        $users = User::with('team')->where('tenant_id', $request->user()->tenant_id)->orderBy('name')->get();

        return Inertia::render('Users/Index', [
            'users' => $users->map(fn (User $u) => [
                'id' => $u->id,
                'username' => $u->username,
                'name' => $u->name,
                'role' => $u->role->label(),
                'team' => $u->team?->name,
                'marketerCode' => $u->marketer_code,
                'isActive' => $u->is_active,
                'lastLoginAt' => $u->last_login_at?->toIso8601String(),
            ]),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Users/Form', $this->formOptions(null));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request, null);
        $user = User::create($data + ['tenant_id' => $request->user()->tenant_id]);
        $this->audit->log('user.created', $user, null, $user->only(self::AUDITED));

        return redirect()->route('users.index')->with('success', 'کاربر ایجاد شد.');
    }

    public function edit(Request $request, User $user): Response
    {
        $this->authorizeTenant($request, $user);

        return Inertia::render('Users/Form', $this->formOptions($user));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->authorizeTenant($request, $user);
        $data = $this->validated($request, $user);
        if (empty($data['password'])) {
            unset($data['password']);
        }
        if ($user->is($request->user()) && (($data['role'] ?? null) !== Role::Admin->value || ! ($data['is_active'] ?? true))) {
            return back()->with('error', 'نمی‌توانید نقش مدیر سامانه یا فعال‌بودن حساب خودتان را تغییر دهید.');
        }

        $before = $user->only(self::AUDITED);
        $user->update($data);
        $this->audit->log('user.updated', $user, $before, $user->only(self::AUDITED), isset($data['password']) ? 'password changed' : null);

        return redirect()->route('users.index')->with('success', 'تغییرات ذخیره شد.');
    }

    private function validated(Request $request, ?User $user): array
    {
        $tenantId = $request->user()->tenant_id;

        $data = $request->validate([
            'username' => ['required', 'string', 'min:3', 'max:64', 'regex:/^[a-zA-Z0-9._-]+$/', Rule::unique('users')->ignore($user?->id)],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:190'],
            'role' => ['required', new Enum(Role::class)],
            'team_id' => ['nullable', Rule::exists('teams', 'id')->where('tenant_id', $tenantId)],
            'marketer_code' => ['nullable', 'string', 'max:32', Rule::unique('users')->where('tenant_id', $tenantId)->ignore($user?->id)],
            'sales_started_on' => ['nullable', 'date'],
            'is_active' => ['boolean'],
            'password' => [$user ? 'nullable' : 'required', 'string', Password::min(10)->letters()->numbers()],
        ], [
            'username.regex' => 'نام کاربری فقط حروف انگلیسی، عدد، نقطه، خط تیره و زیرخط.',
        ]);
        $data['marketer_code'] = $data['marketer_code'] !== null && $data['marketer_code'] !== '' ? trim($data['marketer_code']) : null;

        return $data;
    }

    private function formOptions(?User $user): array
    {
        return [
            'user' => $user ? [
                'id' => $user->id,
                'username' => $user->username,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role->value,
                'team_id' => $user->team_id,
                'marketer_code' => $user->marketer_code,
                'sales_started_on' => $user->sales_started_on?->toDateString(),
                'is_active' => $user->is_active,
            ] : null,
            'roles' => Role::options(),
            'teams' => Team::query()->orderBy('name')->get(['id', 'name']),
        ];
    }

    private function authorizeTenant(Request $request, User $user): void
    {
        abort_unless($user->tenant_id === $request->user()->tenant_id, 404);
    }
}
