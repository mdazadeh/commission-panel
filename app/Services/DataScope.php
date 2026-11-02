<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Row-level visibility of sales data (brief §2, AT-08):
 *   all  → every row of the tenant; team → the user's team members; own → only the user's rows.
 * Applied on the server for pages, drill-downs and exports alike.
 */
class DataScope
{
    public function level(User $user): string
    {
        return match (true) {
            $user->hasPermission('dashboard.all') => 'all',
            $user->hasPermission('dashboard.team') => 'team',
            $user->hasPermission('dashboard.own') => 'own',
            default => 'none',
        };
    }

    /** @return list<int>|null null = unrestricted */
    public function visibleUserIds(User $user): ?array
    {
        return match ($this->level($user)) {
            'all' => null,
            'team' => $user->team_id
                ? User::where('tenant_id', $user->tenant_id)->where('team_id', $user->team_id)->pluck('id')->push($user->id)->unique()->values()->all()
                : [$user->id],
            'own' => [$user->id],
            default => [],
        };
    }

    public function apply(Builder $query, User $user, string $column = 'user_id'): Builder
    {
        $ids = $this->visibleUserIds($user);

        return $ids === null ? $query : $query->whereIn($column, $ids);
    }
}
