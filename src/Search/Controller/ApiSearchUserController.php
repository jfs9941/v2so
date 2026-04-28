<?php

namespace Module\Search\Controller;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Module\Search\Resource\SearchUserResource;
use Module\Search\Service\UserSearchService;

class ApiSearchUserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'query' => 'required|string|min:2',
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:30',
        ]);

        $service = new UserSearchService();
        $results = $service->search(
            $request->get('query'),
            Auth::id(),
            (int) $request->get('page', 1),
            (int) $request->get('per_page', 5)
        );

        $users = $results->getCollection()->map(function ($user) {
            return SearchUserResource::format($user);
        })->values()->toArray();

        return response()->json([
            'success' => true,
            'data' => $users,
            'pagination' => [
                'current_page' => $results->currentPage(),
                'per_page' => $results->perPage(),
                'has_more' => $results->hasMorePages(),
                'total' => $results->total(),
            ],
        ]);
    }
}
