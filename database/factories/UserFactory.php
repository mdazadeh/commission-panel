<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<User> */
class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::query()->value('id') ?? Tenant::create(['code' => 'faran', 'name' => 'فاران'])->id,
            'username' => 'u'.Str::lower(Str::random(8)),
            'name' => fake()->name(),
            'password' => 'password-123',
            'role' => Role::SalesExpert,
            'is_active' => true,
        ];
    }

    public function role(Role $role): static
    {
        return $this->state(['role' => $role]);
    }
}
