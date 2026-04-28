<?php

namespace Module\MediaResolver;

use App\Model\Attachment;
use Illuminate\Support\Facades\Redis;

/**
 * Base resolver for general media/attachments.
 * Handles images and general file attachments with multiple size variants.
 */
class MediaResolver
{
    public const SET_KEY = 'image:needresize';
    public static function getMediaSizes(Attachment $attachment): array
    {
        $previews = $attachment->generated_previews;
        if (empty($previews) || !isset($previews['small']) || !isset($previews['medium'])) {
            if ($attachment->getTypeOfFile() === 'image') {
                try {
                    Redis::sadd(self::SET_KEY, $attachment->id);
                }catch (\Throwable $exception){
                    // resident to Redis failure, log and continue
                }
            }
            return [];
        }

        return $previews;
    }
}
