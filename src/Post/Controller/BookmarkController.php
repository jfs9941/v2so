<?php

namespace Module\Post\Controller;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Module\Post\DTO\PostQueryParams;
use Module\Post\Service\PostQueryService;
use Module\Profile\Helpers\AttachmentHelper;

class BookmarkController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $service = new PostQueryService();
        $posts = $service->bookmarkedPosts(
            PostQueryParams::make()
                ->forUser(Auth::id())
                ->viewedBy(Auth::user())
                ->withMediaType($request->get('filter'))
                ->withSortOrder($request->get('sort', 'latest'))
                ->page((int) $request->get('page', 1))
        );

        $postsData = collect($posts->items())->map(function ($post) {
            return [
                'id' => $post->id,
                'user_id' => $post->user_id,
                'text' => $post->text,
                'price' => $post->price,
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
                'tips_count' => $post->tips_count ?? 0,
            ];
        })->toArray();

        return response()->json([
            'success' => true,
            'data' => [
                'posts' => $postsData,
                'pagination' => [
                    'current_page' => $posts->currentPage(),
                    'per_page' => $posts->perPage(),
                    'total' => $posts->total(),
                    'has_more_pages' => $posts->hasMorePages(),
                    'next_page_url' => $posts->hasMorePages()
                        ? '/bookmarks?page=' . ($posts->currentPage() + 1)
                        : null,
                    'prev_page_url' => $posts->currentPage() > 1
                        ? '/bookmarks?page=' . ($posts->currentPage() - 1)
                        : null,
                ],
            ],
        ]);
    }
}
