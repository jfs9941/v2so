<?php

namespace Module\Post\Query\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait SortsResults
{
    protected function applyOrdering(Builder $query, ?string $sortOrder, ?string $sortBy = null): void
    {
        // Legacy "top" sort used by feed/search queries
        if ($sortOrder === 'top') {
            $query->withCount('reactions');
            $query->orderBy('comments_count', 'DESC');
            $query->orderBy('reactions_count', 'DESC');

            return;
        }

        // Validate sort direction
        $direction = in_array($sortOrder, ['asc', 'desc'], true) ? $sortOrder : 'desc';

        // Sort-by field mapping (used by profile posts)
        match ($sortBy) {
            'most_liked' => $query->orderBy('reactions_count', $direction),
            'highest_tips' => $query->orderBy('tips_count', $direction),
            default => $query->orderBy('posts.created_at', $direction),
        };
    }
}
