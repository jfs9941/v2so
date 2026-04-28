<?php

namespace Module\MediaGallery\Service;

use App\Model\Media;
use Aws\CloudFront\CloudFrontClient;
use Aws\CloudFront\UrlSigner;
use Illuminate\Support\Facades\Storage;
use Module\Upload\Enum\FileDriver;
use Module\Upload\Enum\MediaTypeEnum;


final class PathResolver
{
    public function __construct(private readonly bool $cdnEnabled = true,
                                private readonly string $s3Url,
                                public readonly string $cdnUrl,
                                private readonly string $keyId,
                                private readonly string $keyPath,
    )
    {

    }



    public function resolvePath(Media|string $media, $driver = FileDriver::S3): string
    {
        if ($media instanceof Media) {
            $media = $media->filename;
        }
        if ($driver === FileDriver::LOCAL) {
            return route('home') . '/' . $media;
        }
        // use cookie for all, prevent not able to cache
//        if (!empty($this->keyId) && !empty($this->keyPath)) {
//            return $this->generatePresignUrl($media);
//        }

        if ($this->cdnEnabled) {
            return trim($this->cdnUrl, '/') . '/' . $media;
        }

        return trim($this->s3Url, '/') . '/' . $media;
    }

    public function resolveThumbnail(Media $media): string
    {
        $thumbnail = $media->thumbnail;
        if ($thumbnail) {
            return $this->url($thumbnail, $media->driver);
        }
        if ($media->thumbnail_id) {
            /** @var Media $thumbnailObject */
            $thumbnailObject = Media::find($media->thumbnail_id);
            if ($thumbnailObject) {
                return $this->resolvePath($thumbnailObject, $thumbnailObject->driver);
            }
        }

        return match ($media->file_type) {
            MediaTypeEnum::IMAGE->value => $this->resolvePath($media, $media->driver),
            MediaTypeEnum::DOCUMENT->value => asset('/img/pdf-preview.svg'),
            default => '',
        };
    }

    private function url(string $path, $driver): string
    {
        return match ($driver) {
            FileDriver::LOCAL->value => route('home') . '/' . $path,
            FileDriver::S3->value => $this->resolvePath($path),
        };
    }

    private function generatePresignUrl(string $path): string
    {
        if (str_starts_with($path, 'https://')) {
            throw new \RuntimeException('can not generate presign url for full url path');
        }
        if (str_contains($path, 'm3u8')) {
            throw new \RuntimeException('can not generate presign url for m3u8 here');
        }
        $expires = now()->addMinutes(60)->timestamp;
        $urlSigner = new UrlSigner($this->keyId, Storage::disk('public')->path($this->keyPath));
        return $urlSigner->getSignedUrl(
            $this->cdnUrl . '/' . $path,
            $expires
        );
    }

    /**
     * @param Media $video
     * @param bool $strict
     * @return array{0: array<string, string>, 1: string, 3: numeric}
     */
    public function resolvePathForHlsVideo(Media $video, bool $strict = false): array
    {
        if (!$video->hls_path) {
            throw new \RuntimeException('can not resolve video not processed yet');
        }
        $expires = now()->addMinutes(360)->timestamp;
        $m3u8File = $video->id . '.m3u8';
        if ($strict) {
            $tsFiles = $this->cdnUrl . '/' . str_replace($m3u8File, '', $video->hls_path);
        } else {
            $tsFiles = $this->cdnUrl . '/v2/hls/';
        }
        $policyForTsFiles = json_encode([
            'Statement' => [
                [
                    'Resource' => sprintf('%s*', $tsFiles),
                    'Condition' => [
                        'DateLessThan' => ['AWS:EpochTime' => $expires],
                    ],
                ],
            ],
        ]);
        $cloudFrontClient = new CloudFrontClient([
            'version' => 'latest',
            'region'  => config('filesystems.disks.s3.region'),
        ]);

        $cookies = $cloudFrontClient->getSignedCookie([
            'key_pair_id' => $this->keyId,
            'private_key' => Storage::disk('public')->path($this->keyPath),
            'policy' => $policyForTsFiles,
        ]);
        return [
            $cookies,
            $this->cdnUrl . '/' . $video->hls_path,
            $expires
        ];
    }

    public function resolvePathForHlsVideos(): array
    {
        $expires = now()->addDays(3)->timestamp;
        $tsFiles = $this->cdnUrl . '/';
        $policyForTsFiles = json_encode([
            'Statement' => [
                [
                    'Resource' => sprintf('%s*', $tsFiles),
                    'Condition' => [
                        'DateLessThan' => ['AWS:EpochTime' => $expires],
                    ],
                ],
            ],
        ]);
        $cloudFrontClient = new CloudFrontClient([
            'version' => 'latest',
            'region'  => config('filesystems.disks.s3.region'),
        ]);

        $cookies = $cloudFrontClient->getSignedCookie([
            'key_pair_id' => $this->keyId,
            'private_key' => Storage::disk('public')->path($this->keyPath),
            'policy' => $policyForTsFiles,
        ]);
        return [
            $cookies,
            $expires
        ];
    }
}