<?php

namespace Module\Profile\Model;

use App\Model\User as BaseUser;
use App\Providers\PostsHelperServiceProvider;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * ProfileUser - Extended User model for Profile module
 *
 * Adds profile-specific attributes and methods to the base User model.
 */
class ProfileUser extends BaseUser
{
    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'stats',
        'relationship',
    ];

    /**
     * Get stats for API responses (posts, followers, following counts).
     */
    public function getStatsAttribute(): array
    {
        return [
            'posts_count' => $this->posts_count ?? $this->posts()->count(),
            'media_count' => PostsHelperServiceProvider::numberMediaForUser($this->id),
            'followers_count' => $this->followers_count ?? $this->userListMembers()->whereHas('userList', function ($q) {
                $q->where('name', 'Following');
            })->count(),
            'following_count' => $this->following_count ?? ($this->lists()->where('name', 'Following')->first()?->members_count ?? 0),
        ];
    }

    /**
     * Get relationship data for profile viewing.
     */
    public function getRelationshipAttribute(): ?array
    {
        if (!auth()->check() || auth()->id() === $this->id) {
            return null;
        }

        $viewer = auth()->user();
        $followingList = $viewer->lists()->where('name', 'Following')->where('type', 'following')->first();
        $isFollowing = $followingList && $followingList->members()->where('user_id', $this->id)->exists();
        $followersList = $this->lists()->where('name', 'Following')->where('type', 'following')->first();
        $isFollowedBy = $followersList && $followersList->members()->where('user_id', $viewer->id)->exists();

        $blockedList = $viewer->lists()->where('type', 'blocked')->first();
        $isBlocked = $blockedList && $blockedList->members()->where('user_id', $this->id)->exists();
        $hasBlocked = $this->lists()->where('type', 'blocked')->first()?->members()->where('user_id', $viewer->id)->exists() ?? false;

        return [
            'following' => $isFollowing,
            'followedBy' => $isFollowedBy,
            'canMessage' => $isFollowing || $viewer->role_id !== 5,
            'isBlocked' => $isBlocked,
            'hasBlocked' => $hasBlocked,
        ];
    }

    /**
     * Get formatted profile data for API responses.
     */
    public function toProfileArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'username' => $this->username,
            'avatar' => $this->avatar,
            'cover' => $this->cover,
            'bio' => '',
            'location' => $this->location,
            'website' => $this->website,
            'is_verified' => $this->is_verified ?? false,
            'is_professional' => $this->is_professional ?? false,
            'paid_profile' => $this->paid_profile ?? false,
            'open_profile' => (bool) ($this->open_profile ?? false),
            'user_role' => $this->userRole?->name ?? 'fan',
            'profile_access_price' => $this->profile_access_price,
            'profile_access_price_3_months' => $this->profile_access_price_3_months,
            'profile_access_price_6_months' => $this->profile_access_price_6_months,
            'profile_access_price_12_months' => $this->profile_access_price_12_months,
            'has_shop_items' => $this->shopItems()->count() > 0,
            'stats' => $this->stats,
        ];
    }

    /**
     * Posts relationship with profile-specific scopes.
     */
    public function profilePosts(): HasMany
    {
        return $this->posts()->with(['attachments', 'user'])->orderBy('created_at', 'desc');
    }
}
