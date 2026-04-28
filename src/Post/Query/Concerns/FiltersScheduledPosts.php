<?php

namespace Module\Post\Query\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait FiltersScheduledPosts
{
    protected function onlyReleasedAndNotExpired(Builder $query): void
    {
        $query->notExpiredAndReleased();
    }
}
