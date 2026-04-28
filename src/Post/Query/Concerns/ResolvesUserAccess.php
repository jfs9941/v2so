<?php

namespace Module\Post\Query\Concerns;

use App\Providers\PostsHelperServiceProvider;

trait ResolvesUserAccess
{
    protected function getActiveSubscriptionIds(int $userId): array
    {
        return PostsHelperServiceProvider::getUserActiveSubs($userId);
    }

    protected function getFreeFollowingIds(int $userId): array
    {
        return PostsHelperServiceProvider::getFreeFollowingProfiles($userId);
    }

    protected function getAccessibleUserIds(int $userId): array
    {
        return array_unique(array_merge(
            $this->getActiveSubscriptionIds($userId),
            $this->getFreeFollowingIds($userId)
        ));
    }
}
