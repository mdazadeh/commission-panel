<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class LoginController extends Controller
{
    private const MAX_ATTEMPTS = 5;

    public function __construct(private AuditLogger $audit) {}

    public function create(): Response
    {
        return Inertia::render('Auth/Login');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'max:64'],
            'password' => ['required', 'string', 'max:200'],
            'remember' => ['boolean'],
        ]);

        $key = 'login:'.Str::lower($data['username']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($key);
            throw ValidationException::withMessages(['username' => "تلاش بیش از حد مجاز. {$seconds} ثانیه دیگر دوباره امتحان کنید."]);
        }

        $ok = Auth::attempt(
            ['username' => $data['username'], 'password' => $data['password'], 'is_active' => true],
            (bool) ($data['remember'] ?? false),
        );

        if (! $ok) {
            RateLimiter::hit($key, 60);
            $tenantId = User::where('username', $data['username'])->value('tenant_id');
            $this->audit->log('auth.failed', null, null, ['username' => $data['username']], null, $tenantId);
            throw ValidationException::withMessages(['username' => 'نام کاربری یا رمز عبور اشتباه است.']);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();
        $user = $request->user();
        $user->forceFill(['last_login_at' => Carbon::now()])->save();
        $this->audit->log('auth.login', $user);

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        if ($request->user()) {
            $this->audit->log('auth.logout', $request->user());
        }
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
