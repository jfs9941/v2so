<?php

namespace Module\Post\Controller;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Module\Post\DTO\PostQueryParams;
use Module\Post\Service\PostQueryService;
use Module\Profile\Helpers\AttachmentHelper;

class FeedController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $service = new PostQueryService();
        $posts = $service->feedPosts(
            PostQueryParams::make()
                ->forUser(Auth::id())
                ->viewedBy(Auth::user())
                ->withMediaType($request->get('filter'))
                ->withSortOrder($request->get('sort', 'latest'))
                ->page((int) $request->get('page', 1))
        );

        $postsData = collect($posts->items())->map(function ($post) {
            return $this->formatPost($post);
        })->toArray();

        return response()->json([
            'success' => true,
            'data' => [
                'posts' => $postsData,
                'pagination' => $this->formatPagination($posts, '/feed/posts'),
            ],
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        $service = new PostQueryService();
        $posts = $service->searchPosts(
            PostQueryParams::make()
                ->forUser(Auth::id())
                ->viewedBy(Auth::user())
                ->withMediaType($request->get('filter'))
                ->withSortOrder($request->get('sort', 'latest'))
                ->withSearch($request->get('q', ''))
                ->page((int) $request->get('page', 1))
        );

        $postsData = collect($posts->items())->map(function ($post) {
            return $this->formatPost($post);
        })->toArray();

        return response()->json([
            'success' => true,
            'data' => [
                'posts' => $postsData,
                'pagination' => $this->formatPagination($posts, '/feed/search'),
            ],
        ]);
    }

    private function formatPost($post): array
    {
        return [
            'id' => $post->id,
            'user_id' => $post->user_id,
            'text' => $post->text,
            'price' => $post->price,
            'is_pinned' => (bool) $post->is_pinned,
            'is_public' => (bool) $post->is_public,
            'created_at' => $post->created_at?->toDateTimeString(),
            'user' => $post->user ? [
                'id' => $post->user->id,
                'name' => $post->user->name,
                'username' => $post->user->username,
                'avatar' => $post->user->avatar,
            ] : null,
            'attachments' => $post->attachments?->map(function ($attachment) {
                return AttachmentHelper::format($attachment);
            })->toArray() ?? [],
            'reactions_count' => $post->reactions?->count() ?? 0,
            'comments_count' => $post->comments_count ?? 0,
            'tips_count' => $post->tips_count ?? 0,
        ];
    }

    private function formatPagination($paginator, string $basePath): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'has_more_pages' => $paginator->hasMorePages(),
            'next_page_url' => $paginator->hasMorePages()
                ? $basePath . '?page=' . ($paginator->currentPage() + 1)
                : null,
            'prev_page_url' => $paginator->currentPage() > 1
                ? $basePath . '?page=' . ($paginator->currentPage() - 1)
                : null,
        ];
    }
}
