<?php

namespace Module\Post\Resource;

use Illuminate\Pagination\LengthAwarePaginator;

class PaginationResource
{
    /**
     * Format a paginator for API/Inertia responses.
     */
    public static function format(LengthAwarePaginator $paginator, ?string $basePath = null): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
            'count' => $paginator->count(),
            'has_more' => $paginator->hasMorePages(),
            'next_page_url' => $paginator->hasMorePages() && $basePath
                ? $basePath . '?page=' . ($paginator->currentPage() + 1)
                : null,
            'prev_page_url' => $paginator->currentPage() > 1 && $basePath
                ? $basePath . '?page=' . ($paginator->currentPage() - 1)
                : null,
        ];
    }
}
