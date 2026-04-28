<?php

namespace Module\Post\Query\Concerns;

use App\Providers\AttachmentServiceProvider;
use Illuminate\Database\Eloquent\Builder;

trait FiltersMediaType
{
    protected function filterByMediaType(Builder $query, ?string $mediaType): void
    {
        if (!$mediaType || $mediaType === 'allposts') {
            return;
        }

        if ($mediaType === 'private') {
            $query->where('is_public', 0);

            return;
        }

        if ($mediaType !== 'media') {
            $mediaTypes = AttachmentServiceProvider::getTypeByExtension($mediaType);
            $query->whereHas('attachments', function ($q) use ($mediaTypes) {
                $q->whereIn('type', $mediaTypes);
            });
        }
    }
}
