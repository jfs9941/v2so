<?php

namespace Module\MediaGallery;

use Illuminate\Support\ServiceProvider;
use Module\MediaGallery\Service\PathResolver;

class MediaGalleryProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->bind(PathResolver::class, function () {
            return new PathResolver(
                config('upload.cdn_enabled', false),
                config('upload.s3_base_url'),
                config('upload.cdn_base_url', 'https://cdn.example.com'),
                config('upload.cdn_key', ''),
                config('upload.cdn_path', ''),
            );
        });

    }
}