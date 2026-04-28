<?php

namespace Module\Post\Resource;

use App\Model\Post;
use App\Providers\ListsHelperServiceProvider;
use App\Providers\PostsHelperServiceProvider;
use App\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Module\Profile\Helpers\AttachmentHelper;

class PostResource
{
    public static function format(Post $post): array
    {
        $currentUserId = Auth::id();
        $currentUser = Auth::user();
        $author = $post->user;

        // Determine subscription/access status
        $isSubbed = false;

        $isMentioned = false;
        if ($currentUser) {
            if ($currentUser->role_id === 1) {
                // Admin can see everything
                $isSubbed = true;
            } elseif ($currentUser->id === $author->id) {
                // Own post
                $isSubbed = true;
            } elseif((getSetting('profiles.allow_users_enabling_open_profiles') && $author->open_profile) && ListsHelperServiceProvider::loggedUserIsFollowingUser($author)) {
                $isSubbed = true;
            }
            elseif (!$author->paid_profile && ListsHelperServiceProvider::loggedUserIsFollowingUser($author->id)) {
                $isSubbed = true;
            }
            else {
                $isSubbed = PostsHelperServiceProvider::hasActiveSub($currentUser->id, $author->id);
            }

            // Check if viewer is a mentioned creator
            if ($currentUser->role_id === 2 && is_array($post->tagged_users) && in_array($currentUser->id, $post->tagged_users)) {
                $isSubbed = true;
                $isMentioned = true;
            }
        }

        if ($post->is_public && ($post->price == 0 || $post->price === null)) {
            $isSubbed = true; // Public free post, everyone has access
        }

        if($post->price > 0 && ($currentUser && !PostsHelperServiceProvider::userPaidForPost($currentUser->id, $post->id))) {
            $isSubbed = false;
        }

        // Determine if post content is unlocked (free, subscribed, or purchased)
        $hasUnlocked = $isSubbed || ($post->relationLoaded('postPurchases') && PostsHelperServiceProvider::hasUserUnlockedPost($post->postPurchases));

        // Calculate viewsCount for video posts
        $viewsCount = null;
        if ($post->relationLoaded('attachments')) {
            $hasVideo = $post->attachments->contains(fn ($a) => $a->getTypeOfFile() === 'video');
            if ($hasVideo) {
                $viewsCount = $post->views_count ?? 0;
            }
        }

        return [
            'id' => (string) $post->id,
            'author' => $post->relationLoaded('user') && $post->user
                ? UserResource::formatCompact($post->user)
                : null,
            'content' => $post->text,
            'media' => $post->relationLoaded('attachments')
                ? $post->attachments->map(fn ($a) => AttachmentHelper::format($a, $hasUnlocked))->toArray()
                : [],
            'createdAt' => $post->created_at?->toIso8601String(),
            'updatedAt' => $post->updated_at?->toIso8601String(),
            'scheduledFor' => $post->release_date && \Carbon\Carbon::parse($post->release_date)->isFuture()
                ? \Carbon\Carbon::parse($post->release_date)->toIso8601String()
                : null,
            'stats' => [
                'likesCount' => $post->relationLoaded('reactions')
                    ? $post->reactions->count() : 0,
                'commentsCount' => $post->comments_count ?? 0,
                'repostsCount' => 0,
                'sharesCount' => 0,
                'viewsCount' => $viewsCount,
            ],
            'userActions' => $currentUserId ? [
                'liked' => $post->relationLoaded('reactions')
                    && $post->reactions->contains('user_id', $currentUserId),
                'reposted' => false,
                'bookmarked' => $post->relationLoaded('bookmarks')
                    && $post->bookmarks->contains('user_id', $currentUserId),
                'commented' => false,
            ] : null,
            'visibility' => $post->is_public ? 'public' : 'followers',
            'replyTo' => null, // TODO: Implement when reply feature is added
            'repostOf' => null, // TODO: Implement when repost feature is added
            // Internal fields (not in schema, kept for backward compatibility)
            '_access' => [
                'isSubscribed' => $isSubbed,
                'isMentioned' => $isMentioned,
                'hasUnlocked' => $hasUnlocked,
            ],
            '_price' => (float) $post->price,
            '_isPinned' => (bool) $post->is_pinned,
        ];
    }

    public static function collection(LengthAwarePaginator $paginator): array
    {
        return collect($paginator->items())->map(fn (Post $post) => self::format($post))->toArray();
    }
}
