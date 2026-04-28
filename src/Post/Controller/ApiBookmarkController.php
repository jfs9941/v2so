<?php

namespace Module\Post\Controller;

use App\Http\Controllers\Controller;
use App\Model\Post;
use App\Model\UserBookmark;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Module\Post\DTO\PostQueryParams;
use Module\Post\Resource\PaginationResource;
use Module\Post\Resource\PostResource;
use Module\Post\Service\PostQueryService;

class ApiBookmarkController extends Controller
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

        return response()->json([
            'success' => true,
            'data' => [
                'posts' => PostResource::collection($posts),
                'pagination' => PaginationResource::format($posts, '/bookmarks'),
            ],
        ]);
    }

    /**
     * POST /api/posts/{postId}/bookmark
     *
     * Bookmark a post. Idempotent.
     */
    public function store(string $postId): JsonResponse
    {
        $post = Post::find($postId);
        if (!$post) {
            return response()->json(['success' => false, 'message' => 'Post not found'], 404);
        }

        UserBookmark::firstOrCreate([
            'user_id' => Auth::id(),
            'post_id' => $post->id,
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'post' => [
                    'id' => (string) $post->id,
                    'bookmarked' => true,
                ],
            ],
        ]);
    }

    /**
     * DELETE /api/posts/{postId}/bookmark
     *
     * Remove bookmark. Idempotent.
     */
    public function destroy(string $postId): JsonResponse
    {
        $post = Post::find($postId);
        if (!$post) {
            return response()->json(['success' => false, 'message' => 'Post not found'], 404);
        }

        UserBookmark::where('user_id', Auth::id())
            ->where('post_id', $post->id)
            ->delete();

        return response()->json([
            'success' => true,
            'data' => [
                'post' => [
                    'id' => (string) $post->id,
                    'bookmarked' => false,
                ],
            ],
        ]);
    }
}
