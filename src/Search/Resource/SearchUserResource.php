<?php

namespace Module\Search\Resource;

use App\User;
use Module\MediaResolver\ImagePathSizeResolver;

class SearchUserResource
{
    public static function format(User $user): array
    {
        return [
            'id' => (string) $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'avatar' => ImagePathSizeResolver::getAvatar($user),
            'cover' => $user->cover,
            'bio' => $user->bio,
            'isVerified' => (bool) $user->email_verified_at && $user->birthdate && ($user->verification && $user->verification->status == 'verified'),
            'stats' => [
                'postsCount' => $user->posts_count ?? 0,
                'mediaCount' => \App\Providers\PostsHelperServiceProvider::numberMediaForUser($user->id),
                'followersCount' => $user->activeSubscribers?->count() ?? 0,
            ],
        ];
    }
}
