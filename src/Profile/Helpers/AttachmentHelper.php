<?php

namespace Module\Profile\Helpers;

use App\Model\Attachment;
use App\Providers\AttachmentServiceProvider;
use Module\MediaGallery\Service\PathResolver;
use Module\MediaResolver\ImagePathSizeResolver;
use Module\MediaResolver\MediaResolver;

/**
 * Attachment Helper
 *
 * Formats Attachment model for API responses matching frontend schema.
 * Located in frontend/types/post.ts as PostMedia interface.
 *
 * Schema fields:
 * - id: string
 * - type: 'image' | 'video'
 * - url: string (original quality URL)
 * - thumbnail?: string (for videos)
 * - variants?: { small: string, medium: string } (optimized image variants)
 * - alt?: string
 * - width?: number
 * - height?: number
 * - orientation?: 'portrait' | 'landscape' | 'square'
 * - size?: number (file size in bytes)
 * - duration?: number (video duration in seconds)
 */
class AttachmentHelper
{
    /**
     * Format Attachment model to API response array.
     *
     * @param Attachment $attachment
     * @param bool $hasUnlocked Whether the user has access to the full content
     * @return array
     */
    public static function format(Attachment $attachment, bool $hasUnlocked = true): array
    {
        $attributes = $attachment->getAttributes();
        $isVideo = $attachment->getTypeOfFile() === 'video';
        $locked = !$hasUnlocked;

        // Resolve URLs: full content only when unlocked, thumbnail always (for blur preview)
        $url = null;
        $thumbnail = null;
        $variants = null;

        if ($isVideo) {
            $thumbnailPath = $attachment->getThumbnailAsPath();
            if (!$locked) {
                $url = $attachment->getPlayerUrlAttribute();
            }

            $thumbnail = !empty($thumbnailPath) ? ImagePathSizeResolver::getSizes($thumbnailPath) : null;

        } else {
            $allVariants = self::resolveVariants($attachment, $attributes['filename']);
            // TODO: fix soon
//            if (!$locked) {
//                $url = app(PathResolver::class)->resolvePath($attributes['filename']);
//                $variants = $allVariants;
//            } else {
//                // Locked: only expose small variant for blur preview
//                $variants = $allVariants ? ['small' => $allVariants['small']] : null;
//            }
            $url = app(PathResolver::class)->resolvePath($attributes['filename']);
            $variants = $allVariants;
        }

        // Parse resolution (always returned for layout)
        $width = null;
        $height = null;
        $orientation = null;
        $resolution = $attributes['resolution'] ?? null;

        if ($resolution) {
            [$width, $height] = explode('x', $resolution);
            $width = (int) $width;
            $height = (int) $height;
            $orientation = $attachment->getOrientation();
        }

        return [
            'id' => (string) $attributes['id'],
            'type' => $attachment->getTypeOfFile(),
            'url' => $url,
            'original_url' => !$locked ? $attachment->getPathAttribute() : null,
            'thumbnail' => $thumbnail,
            'variants' => $variants,
            'locked' => $locked,
            'alt' => '', // No alt text stored currently
            'width' => $width,
            'height' => $height,
            'orientation' => $orientation,
            'size' => isset($attributes['size']) ? (int) $attributes['size'] : null,
            'duration' => $isVideo && isset($attributes['duration']) ? (float) $attributes['duration'] : null,
        ];
    }

    /**
     * Resolve image variants (small and medium).
     *
     * @param Attachment $attachment
     * @param string $originalFilename
     * @return array|null
     */
    private static function resolveVariants(Attachment $attachment, string $originalFilename): ?array
    {
        // Check for generated previews first
        $previews = MediaResolver::getMediaSizes($attachment);

        if (!empty($previews)) {
            return [
                'small' => AttachmentServiceProvider::getCdnUrl($previews['small'] ?? $originalFilename),
                'medium' => AttachmentServiceProvider::getCdnUrl($previews['medium'] ?? $originalFilename),
                'original' => AttachmentServiceProvider::getCdnUrl($originalFilename),
            ];
        }

        // Fallback: construct variant paths based on naming convention
        // Pattern: original.jpg -> small/original.jpg, med/original.jpg
        $path = pathinfo($originalFilename, PATHINFO_DIRNAME);
        $filename = pathinfo($originalFilename, PATHINFO_FILENAME);
        $extension = pathinfo($originalFilename, PATHINFO_EXTENSION);

        $cdnUrl = config('upload.cdn_base_url') ?: '';

        return [
            'small' => trim($cdnUrl, '/') . '/' . $path . '/small/' . $filename . '.' . $extension,
            'medium' => trim($cdnUrl, '/') . '/' . $path . '/medium/' . $filename . '.' . $extension,
            'original' => trim($cdnUrl, '/') . '/' . $originalFilename,
        ];
    }
}
