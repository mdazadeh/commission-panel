<?php

namespace App\Models;

use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Users are intentionally NOT tenant-scoped by a global scope: authentication must resolve
 * a user before a tenant is known. Controllers scope user listings explicitly.
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    protected $fillable = [
        'tenant_id', 'username', 'name', 'email', 'password', 'role',
        'team_id', 'marketer_code', 'sales_started_on', 'is_active',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $attributes = ['is_active' => true];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'role' => Role::class,
            'is_active' => 'boolean',
            'sales_started_on' => 'date',
            'last_login_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function hasPermission(string $permission): bool
    {
        return $this->is_active && $this->role->can($permission);
    }
}
