<?php

namespace Module\UserAction\Controller;

use App\Http\Controllers\Controller;
use App\Model\UserList;
use App\Model\UserListMember;
use App\Providers\ListsHelperServiceProvider;
use App\User;
use Illuminate\Support\Facades\Auth;

class ApiBlockUserController extends Controller
{
    /**
     * Toggle block/unblock a user.
     *
     * POST /api/users/{userId}/block
     */
    public function toggle(string $userId)
    {
        $target = User::find($userId);
        if (!$target) {
            return response()->json(['success' => false, 'message' => 'User not found'], 404);
        }

        $authId = Auth::id();

        // Get or create the blocked list
        $blockedList = UserList::firstOrCreate(
            ['user_id' => $authId, 'type' => UserList::BLOCKED_TYPE],
            ['name' => 'Blocked']
        );

        $existing = UserListMember::where('list_id', $blockedList->id)
            ->where('user_id', $userId)
            ->first();

        if ($existing) {
            ListsHelperServiceProvider::deleteListMember($blockedList->id, $userId, false);
            return response()->json([
                'success' => true,
                'message' => 'User unblocked',
                'data' => ['blocked' => false],
            ]);
        }

        ListsHelperServiceProvider::addListMember($blockedList->id, $userId, false);
        return response()->json([
            'success' => true,
            'message' => 'User blocked',
            'data' => ['blocked' => true],
        ]);
    }
}
