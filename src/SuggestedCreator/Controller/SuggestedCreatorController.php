<?php

namespace Module\SuggestedCreator\Controller;

use App\Http\Controllers\Controller;
use App\Providers\MembersHelperServiceProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Module\MediaResolver\ImagePathSizeResolver;

class SuggestedCreatorController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'limit' => 'sometimes|integer|min:1|max:20',
        ]);

        $limit = (int) $request->get('limit', 15);

        $members = MembersHelperServiceProvider::getSuggestedMembers(false);

        $data = $members->take($limit)->map(function ($user) {
            return [
                'id' => (string) $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'avatar' => ImagePathSizeResolver::getAvatar($user),
                'cover' => ImagePathSizeResolver::getCover($user),
                'subscription_price' => (float) ($user->profile_access_price ?? 0),
            ];
        })->values()->toArray();

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }
}
