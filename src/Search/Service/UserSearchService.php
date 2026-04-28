<?php

namespace Module\Search\Service;

use App\User;
use Illuminate\Pagination\LengthAwarePaginator;

class UserSearchService
{
    public function search(string $query, int $authUserId, int $page = 1, int $perPage = 5): LengthAwarePaginator
    {
        $term = str_replace(['%', '_'], ['\\%', '\\_'], $query);

        return User::query()
            ->where('is_active', 1)
            ->where('id', '<>', $authUserId)
            ->where(function ($q) use ($term) {
                $q->where('username', 'LIKE', "%{$term}%")
                    ->orWhere('name', 'LIKE', "%{$term}%");
            })
            ->orderByRaw('CASE WHEN username = ? THEN 0 WHEN username LIKE ? THEN 1 ELSE 2 END', [$term, "{$term}%"])
            ->paginate($perPage, ['*'], 'page', $page);
    }
}
