<?php

namespace Module\Post\Controller;

use App\Http\Controllers\Controller;
use App\Model\Post;
use App\Model\Reaction;
use App\Providers\NotificationServiceProvider;
use Illuminate\Support\Facades\Auth;

class ApiPostLikeController extends Controller
{
    /**
     * Like a post. Idempotent — duplicate likes return current state.
     *
     * POST /api/posts/{postId}/like
     */
    public function like(string $postId)
    {
        $post = Post::find($postId);
        if (!$post) {
            return response()->json(['success' => false, 'message' => 'Post not found'], 404);
        }

        $userId = Auth::id();

        $existing = Reaction::where('user_id', $userId)
            ->where('post_id', $post->id)
            ->first();

        if (!$existing) {
            $reaction = Reaction::create([
                'user_id' => $userId,
                'post_id' => $post->id,
                'reaction_type' => Reaction::TYPE_LIKE,
            ]);

            NotificationServiceProvider::createNewReactionNotification($reaction);
        }

        $likesCount = Reaction::where('post_id', $post->id)->count();

        return response()->json([
            'success' => true,
            'data' => [
                'post' => [
                    'id' => (string) $post->id,
                    'liked' => true,
                    'likesCount' => $likesCount,
                ],
            ],
        ]);
    }

    /**
     * Unlike a post. Idempotent — unliking an already unliked post returns current state.
     *
     * DELETE /api/posts/{postId}/like
     */
    public function unlike(string $postId)
    {
        $post = Post::find($postId);
        if (!$post) {
            return response()->json(['success' => false, 'message' => 'Post not found'], 404);
        }

        $userId = Auth::id();

        Reaction::where('user_id', $userId)
            ->where('post_id', $post->id)
            ->delete();

        $likesCount = Reaction::where('post_id', $post->id)->count();

        return response()->json([
            'success' => true,
            'data' => [
                'post' => [
                    'id' => (string) $post->id,
                    'liked' => false,
                    'likesCount' => $likesCount,
                ],
            ],
        ]);
    }
}
