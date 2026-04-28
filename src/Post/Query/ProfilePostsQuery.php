<?php

namespace Module\Post\Query;

use Module\Post\Model\Post;
use Illuminate\Pagination\LengthAwarePaginator;
use Module\Post\DTO\PostQueryParams;
use Module\Post\Query\Concerns\FiltersApprovedPosts;
use Module\Post\Query\Concerns\FiltersBlockedUsers;
use Module\Post\Query\Concerns\FiltersMediaType;
use Module\Post\Query\Concerns\FiltersScheduledPosts;
use Module\Post\Query\Concerns\Paginates;
use Module\Post\Query\Concerns\SortsResults;

class ProfilePostsQuery
{
    use FiltersBlockedUsers;
    use FiltersApprovedPosts;
    use FiltersScheduledPosts;
    use FiltersMediaType;
    use SortsResults;
    use Paginates;

    public function __construct(private PostQueryParams $params)
    {
    }

    public function get(): LengthAwarePaginator
    {
        $query = Post::query()
            ->with(['user', 'reactions', 'attachments', 'bookmarks', 'postPurchases'])
            ->withCount(['reactions', 'tips', 'activeComments as comments_count']);

        // Profile: only this user's posts
        $query->where('user_id', $this->params->userId);

        // Owner and admin see everything; others see only approved + released
        if (!$this->isOwnerOrAdmin()) {
            $this->onlyApprovedPosts($query, $this->params->viewer);
            $this->onlyReleasedAndNotExpired($query);
        }

        $this->excludeBlockedUsers($query, $this->params->viewer);
        $this->filterByMediaType($query, $this->params->mediaType);

        // Text search
        if ($this->params->searchTerm) {
            $query->where('text', 'LIKE', '%' . $this->params->searchTerm . '%');
        }

        $this->applyOrdering($query, $this->params->sortOrder, $this->params->sortBy);

        return $this->paginate($query, $this->params->page, $this->params->perPage);
    }

    private function isOwnerOrAdmin(): bool
    {
        $viewer = $this->params->viewer;
        if (!$viewer) {
            return false;
        }

        return $viewer->id === $this->params->userId || $viewer->role_id === 1;
    }
}
