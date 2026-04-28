<?php

namespace Module\Post\Query\Concerns;

use App\Model\UserList;
use App\Providers\ListsHelperServiceProvider;
use App\User;
use Illuminate\Database\Eloquent\Builder;

trait FiltersBlockedUsers
{
    protected function excludeBlockedUsers(Builder $query, ?User $viewer): void
    {
        if (!$viewer) {
            return;
        }

        // Users I blocked
        $myBlockedList = $viewer->lists?->firstWhere('type', 'blocked');
        if ($myBlockedList) {
            $blockedByMe = ListsHelperServiceProvider::getListMembers($myBlockedList->id);
            if (!empty($blockedByMe)) {
                $query->whereNotIn('posts.user_id', $blockedByMe);
            }
        }

        // Users who blocked me
        $blockedMeIds = UserList::where('type', UserList::BLOCKED_TYPE)
            ->whereHas('members', function ($q) use ($viewer) {
                $q->where('user_id', $viewer->id);
            })
            ->pluck('user_id')
            ->toArray();

        if (!empty($blockedMeIds)) {
            $query->whereNotIn('posts.user_id', $blockedMeIds);
        }
    }
}
