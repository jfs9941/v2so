<?php

namespace Module\Post\Resource;

use App\User;
use App\Providers\GenericHelperServiceProvider;
use Module\MediaResolver\ImagePathSizeResolver;

class UserResource
{
    public static function format(User $user): array
    {
        return [
            'id' => (string) $user->id,
            'username' => $user->username,
            'name' => $user->name,
            'avatar' => ImagePathSizeResolver::getAvatar($user),
            'bio' => $user->bio,
            'location' => $user->location,
            'website' => $user->website,
            'isVerified' => (bool) $user->email_verified_at && $user->birthdate && ($user->verification && $user->verification->status == 'verified'),
            'createdAt' => $user->created_at?->toIso8601String(),
            'updatedAt' => $user->updated_at?->toIso8601String(),
            'stats' => [
                'postsCount' => $user->posts_count ?? 0,
                'mediaCount' => \App\Providers\PostsHelperServiceProvider::numberMediaForUser($user->id),
                'followersCount' => $user->activeSubscribers?->count() ?? 0,
                'followingCount' => $user->followingCount ?? 0,
            ],
        ];
    }

    public static function formatCompact(User $user): array
    {
        return [
            'id' => (string) $user->id,
            'username' => $user->username,
            'name' => $user->name,
            'avatar' => ImagePathSizeResolver::getAvatar($user),
            'isVerified' => (bool) $user->email_verified_at && $user->birthdate && ($user->verification && $user->verification->status == 'verified')
        ];
    }
}
