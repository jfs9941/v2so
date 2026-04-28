<?php

namespace Module\Post\Query;

use Module\Post\Model\Post;
use DB;
use Illuminate\Pagination\LengthAwarePaginator;
use Module\Post\DTO\PostQueryParams;
use Module\Post\Query\Concerns\FiltersApprovedPosts;
use Module\Post\Query\Concerns\FiltersBlockedUsers;
use Module\Post\Query\Concerns\FiltersGender;
use Module\Post\Query\Concerns\FiltersMediaType;
use Module\Post\Query\Concerns\FiltersScheduledPosts;
use Module\Post\Query\Concerns\Paginates;
use Module\Post\Query\Concerns\ResolvesUserAccess;
use Module\Post\Query\Concerns\SortsResults;

class SearchPostsQuery
{
    use FiltersApprovedPosts;
    use FiltersBlockedUsers;
    use FiltersScheduledPosts;
    use FiltersMediaType;
    use FiltersGender;
    use ResolvesUserAccess;
    use SortsResults;
    use Paginates;

    public function __construct(private PostQueryParams $params)
    {
    }

    public function get(): LengthAwarePaginator
    {
        $viewer = $this->params->viewer;

        $query = Post::query()
            ->select('posts.*')
            ->with(['user', 'reactions', 'attachments', 'bookmarks', 'postPurchases'])
            ->withCount(['tips', 'activeComments as comments_count']);

        // 1. LEFT JOIN following list (matches original filterPosts 'all')
        $followingListId = $viewer?->lists?->firstWhere('type', 'following')?->id ?? 0;
        $query->leftJoin('user_list_members as following', function ($join) use ($followingListId) {
            $join->on('following.user_id', '=', 'posts.user_id');
            $join->on('following.list_id', '=', DB::raw($followingListId));
        });

        // 2. Exclude blocked users (both directions) - top-level AND
        $this->excludeBlockedUsers($query, $viewer);

        // 3. Feed source: subscribed/following OR public+pinned
        // Wrapped in where() group so block filters and common filters apply to both branches
        $accessibleUserIds = $this->getAccessibleUserIds($this->params->userId);
        $genderIds = $this->resolveGenderIds($viewer);

        if (!empty($genderIds)) {
            $query->join('users as creator', 'posts.user_id', '=', 'creator.id');
        }

        $query->where(function ($q) use ($accessibleUserIds, $genderIds) {
            $q->whereIn('posts.user_id', $accessibleUserIds)
                ->orWhere(function ($q2) use ($genderIds) {
                    $q2->where('is_public', 1)->where('is_pinned', 1);
                    // 4. Gender filter only on public+pinned posts
                    if (!empty($genderIds)) {
                        $q2->whereIn('creator.gender_id', $genderIds);
                    }
                });
        });

        // 5. Search: text + username matching
        if ($this->params->searchTerm) {
            $term = $this->params->searchTerm;
            $query->where(function ($q) use ($term) {
                $q->where('text', 'like', '%' . $term . '%')
                    ->orWhereHas('user', function ($q) use ($term) {
                        $q->where('is_active', 1)
                            ->where(function ($q) use ($term) {
                                $q->where('username', 'like', '%' . $term . '%')
                                    ->orWhere('name', 'like', '%' . $term . '%');
                            });
                    });
            });
        }

        // 7. Common filters
        $this->onlyApprovedPosts($query, $viewer);
        $this->onlyReleasedAndNotExpired($query);
        $this->filterByMediaType($query, $this->params->mediaType);
        $this->applyOrdering($query, $this->params->sortOrder);

        return $this->paginate($query, $this->params->page, $this->params->perPage);
    }
}
