<?php

namespace Module\Post\Query\Concerns;

use App\Model\Post;
use App\User;
use Illuminate\Database\Eloquent\Builder;

trait FiltersApprovedPosts
{
    protected function onlyApprovedPosts(Builder $query, ?User $viewer): void
    {
        // Admin can see all post statuses
        if ($viewer && $viewer->role_id === 1) {
            return;
        }

        $query->where('status', Post::APPROVED_STATUS);
    }
}
