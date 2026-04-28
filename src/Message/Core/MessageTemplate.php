<?php

namespace Module\Message\Core;

use App\Model\Attachment;
use Module\Message\Core\Model\AutomatedMessageSent;

final class MessageTemplate
{
    public function __construct(public bool $enabled, public AutomateScenarioEnum $scenario, public string $message,
                                public array $attachments = [], public int $price = 0)
    {
    }

    public function buildHtmlTemplate()
    {

    }

    public function shouldSendMessage($sender, $receiver): bool
    {
        $lastSent = AutomatedMessageSent::where('sender_id', $sender)
            ->where('receiver_id', $receiver)
            ->where('created_at', '>=', now()->subDays(90)) // Limit to messages sent in the last 90 (3 months) days
            ->count();
        return $this->enabled && !$this->emptyContent() && $lastSent == 0; // enable and not sent before (< 90 days))
    }

    public function emptyContent(): bool
    {
        return empty($this->message) && empty($this->attachments);
    }

    public function getAttachments()
    {
        if (empty($this->attachments)) {
            return collect();
        }
        $attachments = Attachment::whereIn('id', $this->attachments)->with('thumbnailObject')->get();
        return $attachments->map(function (Attachment $attachment) {
            if ($attachment->thumbnailObject) {
                return [
                    'id' => $attachment->id,
                    'thumbnail' => $attachment->thumbnailObject?->thumbnail,
                    'type' => $attachment->getTypeOfFile(),
                    'path' => $attachment->path,
                ];
            }
            return [
                'id' => $attachment->id,
                'thumbnail' => $attachment->thumbnail,
                'type' => $attachment->getTypeOfFile(),
                'path' => $attachment->path,
            ];
        });
    }
}