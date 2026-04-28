<?php

namespace Module\MediaResolver\Controller;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;

class MediaBrokenCollectionController
{
    public function collect(Request $request): JsonResponse
    {
        $urls = $request->input('urls');

        foreach ($urls as $url) {
            Redis::sadd('media:broken_links', $url);
        }

        return response()->json([], 200);
    }
}