<?php

namespace Module\Post\Query\Concerns;

use App\Providers\PostsHelperServiceProvider;
use App\User;
use Illuminate\Database\Eloquent\Builder;

trait FiltersGender
{
    protected function resolveGenderIds(?User $viewer): array
    {
        if (!$viewer) {
            return [];
        }

        if (!$viewer->wants_men && !$viewer->wants_women && !$viewer->wants_trans) {
            return [];
        }

        $genderIds = [];

        if ($viewer->wants_men) {
            $genderIds[] = PostsHelperServiceProvider::MEN_FILTER;
        }
        if ($viewer->wants_women) {
            $genderIds[] = PostsHelperServiceProvider::WOMEN_FILTER;
        }
        if ($viewer->wants_trans) {
            $genderIds[] = PostsHelperServiceProvider::OTHER_FILTER;
        }

        // Couples shown when either men or women preference is set
        if ($viewer->wants_men || $viewer->wants_women) {
            $genderIds[] = PostsHelperServiceProvider::COUPLE_FILTER;
        }

        return array_values(array_unique($genderIds));
    }

    protected function applyGenderFilter(Builder $query, ?User $viewer): void
    {
        $genderIds = $this->resolveGenderIds($viewer);

        if (empty($genderIds)) {
            return;
        }

        $query->join('users as creator', 'posts.user_id', '=', 'creator.id')
            ->whereIn('creator.gender_id', $genderIds);
    }
}
