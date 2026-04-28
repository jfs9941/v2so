<?php

namespace Module\Lists\Controller;

use App\Http\Controllers\Controller;
use App\Model\UserList;
use App\Model\UserListMember;
use App\Providers\ListsHelperServiceProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;

class ApiListController extends Controller
{
    /**
     * Get authenticated user's lists with is_member flag for a target user.
     *
     * GET /api/my/lists?target_user_id=123
     */
    public function index(Request $request): JsonResponse
    {
        $user = JWTAuth::user();
        $targetUserId = $request->get('target_user_id');

        $lists = UserList::where('user_id', $user->id)
            ->whereNotIn('type', [UserList::FOLLOWING_TYPE])
            ->withCount('members')
            ->get();

        $listsData = $lists->map(function (UserList $list) use ($targetUserId) {
            $isMember = false;
            if ($targetUserId) {
                $isMember = UserListMember::where('list_id', $list->id)
                    ->where('user_id', $targetUserId)
                    ->exists();
            }

            return [
                'id' => $list->id,
                'name' => $list->name,
                'type' => $list->type,
                'members_count' => $list->members_count,
                'is_member' => $isMember,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'lists' => $listsData,
            ],
        ]);
    }

    /**
     * Toggle a target user's membership in a list.
     *
     * POST /api/my/lists/{listId}/toggle
     * Body: { user_id: 123 }
     */
    public function toggle(Request $request, int $listId): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
        ]);

        $user = JWTAuth::user();
        $targetUserId = $request->input('user_id');

        // Verify the list belongs to the authenticated user
        $list = UserList::where('id', $listId)
            ->where('user_id', $user->id)
            ->first();

        if (!$list) {
            return response()->json([
                'success' => false,
                'message' => __('List not found.'),
            ], 404);
        }

        // Check if user is already a member
        $existing = UserListMember::where('list_id', $listId)
            ->where('user_id', $targetUserId)
            ->first();

        if ($existing) {
            ListsHelperServiceProvider::deleteListMember($listId, $targetUserId, false);

            return response()->json([
                'success' => true,
                'data' => ['is_member' => false],
                'message' => __('Removed from list.'),
            ]);
        }

        ListsHelperServiceProvider::addListMember($listId, $targetUserId, false);

        return response()->json([
            'success' => true,
            'data' => ['is_member' => true],
            'message' => __('Added to list.'),
        ]);
    }
}
