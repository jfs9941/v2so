<?php

namespace Module\Profile\Model;

use App\Model\Post as BasePost;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * ProfilePost - Extended Post model for Profile module
 *
 * Adds profile-specific scopes and methods to the base Post model.
 */
class ProfilePost extends BasePost
{
    /**
     * Scope for profile API - loads posts with attachments and author.
     */
    public function scopeForProfile($query)
    {
        return $query->with(['attachments', 'user']);
    }

    /**
     * Scope to filter posts that have attachments (media only).
     */
    public function scopeWithMedia($query)
    {
        return $query->whereHas('attachments');
    }

    /**
     * Scope for ordered by latest.
     */
    public function scopeLatest($query)
    {
        return $query->orderBy('created_at', 'desc');
    }

    /**
     * Format post data for API responses.
     */
    public function toProfileArray(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'text' => $this->text,
            'price' => $this->price,
            'created_at' => $this->created_at?->toDateTimeString(),
            'attachments' => $this->attachments?->map(function ($attachment) {
                return $this->formatAttachment($attachment);
            })->toArray() ?? [],
        ];
    }

    /**
     * Format attachment data for API responses.
     */
    protected function formatAttachment($attachment): array
    {
        return [
            'id' => $attachment->id,
            'type' => $attachment->type,
            'attachment_type' => $attachment->attachment_type ?? 'post',
            'thumbnail' => $attachment->thumbnail,
            'path' => $attachment->path,
            'player_url' => $attachment->player_url,
            'orientation' => $attachment->orientation,
            'resolution' => $attachment->resolution,
        ];
    }
}
