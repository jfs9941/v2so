<?php

namespace Module\UserAction\Controller;

use App\Http\Controllers\Controller;
use App\Providers\ListsHelperServiceProvider;
use App\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class ApiFollowController extends Controller
{
    /**
     * Follow a user.
     *
     * POST /api/users/{userId}/follow
     */
    public function follow(string $userId): JsonResponse
    {
        $target = User::find($userId);
        if (!$target) {
            return response()->json(['success' => false, 'message' => 'User not found'], 404);
        }

        $authId = Auth::id();

        if ($authId == $userId) {
            return response()->json(['success' => false, 'message' => 'You cannot follow yourself.'], 400);
        }

        $isFollowing = ListsHelperServiceProvider::isUserFollowing($authId, $userId);

        if (!$isFollowing) {
            ListsHelperServiceProvider::managePredefinedUserMemberList($authId, $userId, 'follow');
        }

        return response()->json([
            'success' => true,
            'data' => [
                'following' => true,
                'followersCount' => $target->followers()->count(),
            ],
        ]);
    }

    /**
     * Unfollow a user.
     *
     * DELETE /api/users/{userId}/follow
     */
    public function unfollow(string $userId): JsonResponse
    {
        $target = User::find($userId);
        if (!$target) {
            return response()->json(['success' => false, 'message' => 'User not found'], 404);
        }

        $authId = Auth::id();

        $isFollowing = ListsHelperServiceProvider::isUserFollowing($authId, $userId);

        if ($isFollowing) {
            ListsHelperServiceProvider::managePredefinedUserMemberList($authId, $userId, 'unfollow');
        }

        return response()->json([
            'success' => true,
            'data' => [
                'following' => false,
                'followersCount' => $target->followers()->count(),
            ],
        ]);
    }
}
