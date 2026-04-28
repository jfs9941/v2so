<?php

namespace Module\Message\Core\Event;

use App\Providers\MessengerProvider;
use App\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Module\Message\Core\AutomateScenarioEnum;
use Module\Message\Core\MessageAutomateSetting;
use Module\Message\Core\Model\AutomatedMessageSent;
use Ramsey\Uuid\Uuid;
use function Ramsey\Uuid\v4;

class SubscribeEventHandler implements ShouldQueue
{
    use InteractsWithQueue;

    public $delay = 5;

    public function handle(SubscribeEvent $event): void
    {
        $creator = User::find($event->creatorId);
        $subscriber = User::find($event->subscriberId);

        if (!$creator || !$subscriber) {
            \Log::warning('[MessageAutomate][SubscribeEventHandler] Creator or Subscriber not found', [
                'creatorId' => $event->creatorId,
                'subscriberId' => $event->subscriberId,
            ]);
            return;
        }

        $automateSettingsService = new MessageAutomateSetting();
        $template = $automateSettingsService->getAutomateTemplate(
            $creator,
            AutomateScenarioEnum::NEW_SUBSCRIPTION
        );

        if ($template->shouldSendMessage($event->creatorId, $event->subscriberId)) {
            $message = $template->message;
            $attachments = $template->attachments;
            $messengerProvider = new MessengerProvider($creator);
            $messengerProvider->sendUserMessage([
                'senderID' => $creator->id,
                'receiverID' => $subscriber->id,
                'messageValue' => $message,
                'messagePrice' => $template->price,
                'attachments' => $attachments,
            ]);
            AutomatedMessageSent::create([
                'id' => Uuid::uuid4()->toString(),
                'sender_id' => $creator->id,
                'receiver_id' => $subscriber->id,
                'sent_at' => now(),
            ]);
        }
    }
}
