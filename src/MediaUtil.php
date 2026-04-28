<?php

namespace Module;

use App\Model\Post;
use Module\MediaGallery\Service\PathResolver;

class MediaUtil
{
    public static function mediaOfPost(Post $post)
    {
        $pathResolver = app(PathResolver::class);
        $medias = $post->medias;
        if ($medias) {
            return collect($medias)->map(function ($media) use ($pathResolver) {
                $view = [
                    'id' => $media->getAttribute('id'),
                    'filename' => $media->getAttribute('filename'),
                    'type' => $media->file_type,
                    'path' => $pathResolver->resolvePath($media, $media->getAttribute('driver')),
                    'thumbnail' => $pathResolver->resolveThumbnail($media),
                ];
                if ($media->getAttribute('hls_path')) {
                    $view['player_url'] = $pathResolver->resolvePathForHlsVideo($media, true);
                } else {
                    $view['player_url'] = $pathResolver->resolvePath($media, $media->getAttribute('driver'));
                }
                return $view;
            });
        }
        return [];
    }
}