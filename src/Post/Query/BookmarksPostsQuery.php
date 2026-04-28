<?php

namespace Module\Post\Query;

use Module\Post\Model\Post;
use DB;
use Illuminate\Pagination\LengthAwarePaginator;
use Module\Post\DTO\PostQueryParams;
use Module\Post\Query\Concerns\FiltersApprovedPosts;
use Module\Post\Query\Concerns\FiltersBlockedUsers;
use Module\Post\Query\Concerns\FiltersMediaType;
use Module\Post\Query\Concerns\FiltersScheduledPosts;
use Module\Post\Query\Concerns\Paginates;
use Module\Post\Query\Concerns\ResolvesUserAccess;
use Module\Post\Query\Concerns\SortsResults;

class BookmarksPostsQuery
{
    use FiltersBlockedUsers;
    use FiltersApprovedPosts;
    use FiltersScheduledPosts;
    use FiltersMediaType;
    use ResolvesUserAccess;
    use SortsResults;
    use Paginates;

    public function __construct(private PostQueryParams $params)
    {
    }

    public function get(): LengthAwarePaginator
    {
        $query = Post::query()
            ->select('posts.*')
            ->with(['user', 'reactions', 'attachments', 'bookmarks', 'postPurchases'])
            ->withCount(['tips', 'activeComments as comments_count']);

        // Bookmarks: join user_bookmarks table
        $userId = $this->params->userId;
        $query->join('user_bookmarks', function ($join) use ($userId) {
                $join->on('user_bookmarks.post_id', '=', 'posts.id')
                    ->where('user_bookmarks.user_id', '=', DB::raw($userId));
            });

        // Only show bookmarked posts the user still has access to
        $accessibleUserIds = $this->getAccessibleUserIds($userId);
        $query->where(function ($q) use ($accessibleUserIds) {
            $q->whereIn('posts.user_id', $accessibleUserIds)
                ->orWhere('posts.is_public', 1);
        });

        // Common filters
        $this->excludeBlockedUsers($query, $this->params->viewer);
        $this->onlyApprovedPosts($query, $this->params->viewer);
        $this->onlyReleasedAndNotExpired($query);
        $this->filterByMediaType($query, $this->params->mediaType);
        $this->applyOrdering($query, $this->params->sortOrder);

        return $this->paginate($query, $this->params->page, $this->params->perPage);
    }
}
