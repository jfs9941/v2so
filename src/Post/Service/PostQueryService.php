<?php

namespace Module\Post\Service;

use Illuminate\Pagination\LengthAwarePaginator;
use Module\Post\DTO\PostQueryParams;
use Module\Post\Query\BookmarksPostsQuery;
use Module\Post\Query\FeedPostsQuery;
use Module\Post\Query\ProfilePostsQuery;
use Module\Post\Query\SearchPostsQuery;

class PostQueryService
{
    public function feedPosts(PostQueryParams $params): LengthAwarePaginator
    {
        return (new FeedPostsQuery($params))->get();
    }

    public function profilePosts(PostQueryParams $params): LengthAwarePaginator
    {
        return (new ProfilePostsQuery($params))->get();
    }

    public function bookmarkedPosts(PostQueryParams $params): LengthAwarePaginator
    {
        return (new BookmarksPostsQuery($params))->get();
    }

    public function searchPosts(PostQueryParams $params): LengthAwarePaginator
    {
        return (new SearchPostsQuery($params))->get();
    }
}
