<?php

namespace Module\Post\Controller;

use App\Http\Controllers\Controller;
use App\Model\Post;
use App\Model\PostComment;
use App\Model\Reaction;
use App\Providers\NotificationServiceProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Module\Post\Resource\CommentResource;

class ApiCommentController extends Controller
{
    /**
     * GET /api/posts/{postId}/comments
     *
     * List comments for a post (flat, no nesting).
     * Auth is optional — guests see likedByUser: false.
     */
    public function index(Request $request, string $postId): JsonResponse
    {
        $post = Post::find($postId);
        if (!$post) {
            return response()->json(['success' => false, 'message' => 'Post not found'], 404);
        }

        $perPage = min((int) $request->get('per_page', 20), 50);

        $comments = PostComment::with(['author', 'reactions'])
            ->where('post_id', $postId)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        $data = collect($comments->items())
            ->map(fn (PostComment $c) => CommentResource::format($c))
            ->toArray();

        return response()->json([
            'success' => true,
            'data' => $data,
            'meta' => [
                'pagination' => [
                    'current_page' => $comments->currentPage(),
                    'per_page' => $comments->perPage(),
                    'total' => $comments->total(),
                    'last_page' => $comments->lastPage(),
                    'count' => count($data),
                    'has_more_pages' => $comments->hasMorePages(),
                ],
            ],
        ]);
    }

    /**
     * POST /api/posts/{postId}/comments
     *
     * Create a new comment.
     */
    public function store(Request $request, string $postId): JsonResponse
    {
        $post = Post::find($postId);
        if (!$post) {
            return response()->json(['success' => false, 'message' => 'Post not found'], 404);
        }

        $validated = $request->validate([
            'content' => 'required|string|max:1500',
        ]);

        $comment = PostComment::create([
            'user_id' => Auth::id(),
            'post_id' => $post->id,
            'message' => $validated['content'],
        ]);

        // Send notification if commenter is not the post owner
        if (Auth::id() != $post->user_id) {
            NotificationServiceProvider::createNewPostCommentNotification($comment);
        }

        $comment->load(['author', 'reactions']);

        return response()->json([
            'success' => true,
            'data' => [
                'comment' => CommentResource::format($comment),
            ],
        ], 201);
    }

    /**
     * PUT /api/comments/{commentId}
     *
     * Update a comment's content. Only the author can edit.
     */
    public function update(Request $request, string $commentId): JsonResponse
    {
        $comment = PostComment::find($commentId);
        if (!$comment) {
            return response()->json(['success' => false, 'message' => 'Comment not found'], 404);
        }

        if ($comment->is_deleted) {
            return response()->json(['success' => false, 'message' => 'Cannot edit deleted comment'], 410);
        }

        if (Auth::id() != $comment->user_id) {
            return response()->json(['success' => false, 'message' => 'You can only edit your own comments'], 403);
        }

        $validated = $request->validate([
            'content' => 'required|string|max:1500',
        ]);

        $comment->update(['message' => $validated['content']]);
        $comment->load(['author', 'reactions']);

        return response()->json([
            'success' => true,
            'data' => [
                'comment' => CommentResource::format($comment),
            ],
        ]);
    }

    /**
     * DELETE /api/comments/{commentId}
     *
     * Soft-delete a comment. Only author or post owner can delete.
     */
    public function destroy(string $commentId): JsonResponse
    {
        $comment = PostComment::with('post')->find($commentId);
        if (!$comment) {
            return response()->json(['success' => false, 'message' => 'Comment not found'], 404);
        }

        if ($comment->is_deleted) {
            return response()->json(['success' => false, 'message' => 'Comment already deleted'], 410);
        }

        $userId = Auth::id();
        $isOwner = $userId == $comment->user_id;
        $isPostOwner = $comment->post && $userId == $comment->post->user_id;

        if (!$isOwner && !$isPostOwner) {
            return response()->json(['success' => false, 'message' => 'You can only delete your own comments'], 403);
        }

        $comment->update(['is_deleted' => true, 'message' => '']);

        return response()->json([
            'success' => true,
            'message' => 'Comment deleted',
        ]);
    }

    /**
     * POST /api/comments/{commentId}/like
     *
     * Toggle like on a comment. Like if not liked, unlike if already liked.
     */
    public function toggleLike(string $commentId): JsonResponse
    {
        $comment = PostComment::find($commentId);
        if (!$comment) {
            return response()->json(['success' => false, 'message' => 'Comment not found'], 404);
        }

        $userId = Auth::id();

        $existing = Reaction::where('user_id', $userId)
            ->where('post_comment_id', $comment->id)
            ->first();

        if ($existing) {
            $existing->delete();
            $liked = false;
        } else {
            $reaction = Reaction::create([
                'user_id' => $userId,
                'post_comment_id' => $comment->id,
                'reaction_type' => Reaction::TYPE_LIKE,
            ]);

            NotificationServiceProvider::createNewReactionNotification($reaction);
            $liked = true;
        }

        $likesCount = Reaction::where('post_comment_id', $comment->id)->count();

        return response()->json([
            'success' => true,
            'data' => [
                'comment' => [
                    'id' => (string) $comment->id,
                    'liked' => $liked,
                    'likesCount' => $likesCount,
                ],
            ],
        ]);
    }
}
