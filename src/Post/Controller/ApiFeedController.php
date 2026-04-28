<?php

namespace Module\Post\Controller;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Module\Post\DTO\PostQueryParams;
use Module\Post\Resource\PaginationResource;
use Module\Post\Resource\PostResource;
use Module\Post\Service\PostQueryService;

class ApiFeedController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $service = new PostQueryService();
        $posts = $service->feedPosts(
            PostQueryParams::make()
                ->forUser(Auth::id())
                ->viewedBy(Auth::user())
                ->withMediaType($request->get('filter'))
                ->withSortOrder($request->get('sort', 'latest'))
                ->page((int) $request->get('page', 1))
        );

        return response()->json([
            'success' => true,
            'data' => [
                'posts' => PostResource::collection($posts),
                'pagination' => PaginationResource::format($posts, '/feed'),
            ],
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        $service = new PostQueryService();
        $posts = $service->searchPosts(
            PostQueryParams::make()
                ->forUser(Auth::id())
                ->viewedBy(Auth::user())
                ->withMediaType($request->get('filter'))
                ->withSortOrder($request->get('sort', 'latest'))
                ->withSearch($request->get('q', ''))
                ->page((int) $request->get('page', 1))
        );

        return response()->json([
            'success' => true,
            'data' => [
                'posts' => PostResource::collection($posts),
                'pagination' => PaginationResource::format($posts, '/feed/search'),
            ],
        ]);
    }
}
