<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_validation_messages_are_persian(): void
    {
        config(['app.locale' => 'fa']);
        app()->setLocale('fa');
        $admin = $this->bootstrapTenant();

        $this->actingAs($admin)->post('/users', ['username' => 'admin', 'name' => '', 'role' => 'sales_expert', 'password' => 'short'])
            ->assertSessionHasErrors([
                'username' => 'این نام کاربری قبلاً استفاده شده است.',
                'name' => 'نام الزامی است.',
            ]);
    }
}
