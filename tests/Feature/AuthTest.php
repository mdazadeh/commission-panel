<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\AuditEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/')->assertRedirect('/login');
        $this->get('/login')->assertOk();
    }

    public function test_login_with_username_and_audit(): void
    {
        $user = $this->makeUser(Role::SalesExpert, ['username' => 'expert1', 'password' => 'secret-pass-1']);

        $this->post('/login', ['username' => 'expert1', 'password' => 'secret-pass-1'])->assertRedirect('/');
        $this->assertAuthenticatedAs($user);
        $this->assertTrue(AuditEvent::where('action', 'auth.login')->where('user_id', $user->id)->exists());
    }

    public function test_wrong_password_is_rejected_and_throttled(): void
    {
        $this->makeUser(Role::SalesExpert, ['username' => 'expert1', 'password' => 'secret-pass-1']);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['username' => 'expert1', 'password' => 'wrong'])->assertSessionHasErrors('username');
        }
        // Even the correct password is refused while the lock-out is active.
        $this->post('/login', ['username' => 'expert1', 'password' => 'secret-pass-1'])->assertSessionHasErrors('username');
        $this->assertStringContainsString('تلاش بیش از حد مجاز', session('errors')->first('username'));
        $this->assertGuest();
        $this->assertSame(5, AuditEvent::where('action', 'auth.failed')->count());
    }

    public function test_inactive_user_cannot_login(): void
    {
        $this->makeUser(Role::SalesExpert, ['username' => 'gone', 'password' => 'secret-pass-1', 'is_active' => false]);

        $this->post('/login', ['username' => 'gone', 'password' => 'secret-pass-1'])->assertSessionHasErrors('username');
        $this->assertGuest();
    }

    public function test_deactivated_user_is_logged_out_on_next_request(): void
    {
        $user = $this->makeUser(Role::SalesExpert);
        $this->actingAs($user)->get('/')->assertOk();

        $user->update(['is_active' => false]);
        $this->actingAs($user->fresh())->get('/')->assertRedirect('/login');
    }
}
