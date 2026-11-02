<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\AuditEvent;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_creates_and_updates_user_with_audit_trail(): void
    {
        $admin = $this->bootstrapTenant();

        $this->actingAs($admin)->post('/users', [
            'username' => 'm.ramezani', 'name' => 'کارشناس نمونه', 'role' => 'sales_expert',
            'marketer_code' => ' 24 ', 'sales_started_on' => '2024-03-20', 'is_active' => true, 'password' => 'Strong-pass-1',
        ])->assertRedirect('/users');

        $user = User::where('username', 'm.ramezani')->firstOrFail();
        $this->assertSame('24', $user->marketer_code);
        $created = AuditEvent::where('action', 'user.created')->firstOrFail();
        $this->assertSame('sales_expert', $created->after['role']);
        $this->assertArrayNotHasKey('password', $created->after);

        $this->actingAs($admin)->put("/users/{$user->id}", [
            'username' => 'm.ramezani', 'name' => 'کارشناس نمونه', 'role' => 'sales_head', 'marketer_code' => '24', 'is_active' => true,
        ])->assertRedirect('/users');

        $this->assertSame(Role::SalesHead, $user->fresh()->role);
        $updated = AuditEvent::where('action', 'user.updated')->firstOrFail();
        $this->assertSame(['sales_expert', 'sales_head'], [$updated->before['role'], $updated->after['role']]);
        $this->assertTrue(app(AuditLogger::class)->verify()['ok']);
    }

    public function test_marketer_code_is_unique_within_tenant(): void
    {
        $admin = $this->bootstrapTenant();
        $this->makeUser(Role::SalesExpert, ['marketer_code' => '24']);

        $this->actingAs($admin)->post('/users', [
            'username' => 'dup', 'name' => 'x', 'role' => 'sales_expert', 'marketer_code' => '24', 'password' => 'Strong-pass-1',
        ])->assertSessionHasErrors('marketer_code');
    }

    public function test_admin_cannot_demote_or_deactivate_self(): void
    {
        $admin = $this->bootstrapTenant();

        $this->actingAs($admin)->put("/users/{$admin->id}", ['username' => 'admin', 'name' => 'مدیر', 'role' => 'sales_expert', 'is_active' => true])
            ->assertSessionHas('error');
        $this->assertSame(Role::Admin, $admin->fresh()->role);
    }
}
