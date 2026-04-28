<?php

namespace Module\Post\Resource;

use App\Model\PostComment;
use Illuminate\Support\Facades\Auth;

class CommentResource
{
    public static function format(PostComment $comment): array
    {
        $currentUserId = Auth::id();

        $data = [
            'id' => (string) $comment->id,
            'author' => $comment->relationLoaded('author') && $comment->author
                ? UserResource::formatCompact($comment->author)
                : null,
            'content' => $comment->is_deleted ? '' : $comment->message,
            'createdAt' => $comment->created_at?->toIso8601String(),
            'isDeleted' => (bool) $comment->is_deleted,
            'canDelete' => $currentUserId && !$comment->is_deleted && (($currentUserId == $comment->user_id || self::isPostOwner($comment, $currentUserId))),
            'canEdit' => $currentUserId && !$comment->is_deleted && $currentUserId == $comment->user_id,
            'likesCount' => $comment->relationLoaded('reactions')
                ? $comment->reactions->count() : 0,
            'likedByUser' => $currentUserId && $comment->relationLoaded('reactions')
                ? $comment->reactions->contains('user_id', $currentUserId)
                : false,
            'postId' => (string) $comment->post_id,
        ];

        // Only include updatedAt if comment was edited
        if ($comment->updated_at && $comment->updated_at->ne($comment->created_at)) {
            $data['updatedAt'] = $comment->updated_at->toIso8601String();
        }

        return $data;
    }

    private static function isPostOwner(PostComment $comment, int $userId): bool
    {
        if ($comment->relationLoaded('post') && $comment->post) {
            return $comment->post->user_id == $userId;
        }

        return false;
    }
}
