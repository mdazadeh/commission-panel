<?php

namespace App\Http\Controllers\Concerns;

use App\Models\User;
use App\Services\DataScope;

/** Reviewers see their team (head) or everyone (managers) — never themselves (four-eyes principle). */
trait ReviewsTeamItems
{
    /** @return list<int>|null null = all users of the tenant except the reviewer */
    protected function reviewableUserIds(User $reviewer, string $permission): ?array
    {
        if (! $reviewer->hasPermission($permission)) {
            return [];
        }
        $ids = app(DataScope::class)->visibleUserIds($reviewer);

        return $ids === null ? null : array_values(array_diff($ids, [$reviewer->id]));
    }

    protected function canReview(User $reviewer, int $ownerId, string $permission): bool
    {
        if ($ownerId === $reviewer->id) {
            return false;
        }
        $ids = $this->reviewableUserIds($reviewer, $permission);

        return $ids === null || in_array($ownerId, $ids, true);
    }
}
