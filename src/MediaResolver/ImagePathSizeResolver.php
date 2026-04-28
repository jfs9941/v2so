<?php

namespace Module\MediaResolver;

use App\Providers\AttachmentServiceProvider;
use App\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Avatar resolver for user profile images with multiple size variants.
 */
class ImagePathSizeResolver
{
    public static function getSizes(string $path): array
    {
        return array_merge(...array_map(function ($size) use ($path) {
            return  [
                $size => AttachmentServiceProvider::getCdnUrl(self::fs($path,$size))
            ];

        }, ['original', 'medium', 'small']));
    }

    public static function getAvatar(User $user): ?array
    {
        $avatar = $user->getAttributes()['avatar'] ?? null;
        if (!$avatar) {
            return null;
        }

        return array_merge(...array_map(function ($size) use ($avatar) {
            return  [
                $size => AttachmentServiceProvider::getCdnUrl(self::fs($avatar,$size))
            ];

        }, ['original', 'medium', 'small']));
    }

    public static function getCover(User $user): ?array
    {
        $cover = $user->getAttributes()['cover'] ?? null;
        if (!$cover) {
            return null;
        }

        return array_merge(...array_map(function ($size) use ($cover) {
            return  [
                $size => AttachmentServiceProvider::getCdnUrl(self::fs($cover,$size))
            ];

        }, ['original', 'medium', 'small']));
    }

    private static function fs(string $avatar, string $size): string
    {
        $path = pathinfo($avatar, PATHINFO_DIRNAME);
        $filename = pathinfo($avatar, PATHINFO_FILENAME);
        $extension = pathinfo($avatar, PATHINFO_EXTENSION);
        return match ($size) {
            'original' => $avatar,
            'medium' => $path . '/medium/' . $filename . '.' . $extension,
            default => $path . '/small/' . $filename . '.' . $extension,
        };
    }

    public static function resizeAvatar(User $user): void
    {
        try {
            // Default headers for all requests
            $headers = [
                'Authorization' => 'Bearer ' . $user->token(),
                'Accept' => 'application/json',
            ];
            Http::withHeaders($headers)
                ->post(config('upload.upload_api') . '/api/resize',
                    [
                        'paths' => [$user->getAttributes()['avatar']],
                    ]
                );
        }catch (\Throwable $exception){
                // resident to API failure, log and continue
            Log::error('Avatar resize API call failed for user ID ' . $user->id . ': ' . $exception->getMessage());
        }
    }

    public static function resizeCover(User $user): void
    {
        try {
            // Default headers for all requests
            $headers = [
                'Authorization' => 'Bearer ' . $user->token(),
                'Accept' => 'application/json',
            ];
            Http::withHeaders($headers)
                ->post(config('upload.upload_api') . '/api/resize',
                    [
                        'paths' => [$user->getAttributes()['cover']],
                    ]
                );
        }catch (\Throwable $exception){
            // resident to API failure, log and continue
            Log::error('Avatar resize API call failed for user ID ' . $user->id . ': ' . $exception->getMessage());
        }
    }
}
