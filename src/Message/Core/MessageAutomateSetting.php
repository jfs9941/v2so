<?php

namespace Module\Message\Core;

use App\User;

class MessageAutomateSetting
{
    public function getAutomateTemplate(User $user, AutomateScenarioEnum $scenario): MessageTemplate
    {
        $settings = $user->settings ?? collect();
        $messageAutomateSettings = collect($settings->get('message_automate', []));

        $keySetting = $scenario->getKeySetting();
        if (!$messageAutomateSettings->has($keySetting)) {
            return new MessageTemplate(false, $scenario, '');
        }

        $automateSettings = $messageAutomateSettings->get($keySetting);
        if ($automateSettings instanceof \stdClass) {
            $automateSettings = json_decode(json_encode($automateSettings), true);
        }
        return new MessageTemplate($automateSettings['enabled'] ?? false, AutomateScenarioEnum::NEW_SUBSCRIPTION, $automateSettings['message'] ?? '',
            $automateSettings['attachments'] ?? [], $automateSettings['price'] ?? 0);
    }

    public function setAutomateTemplate(User $user, MessageTemplate $template): void
    {
        $settings = $user->settings ?? collect();
        $messageAutomateSettings = collect($settings->get('message_automate', []));

        $keySetting = $template->scenario->getKeySetting();

        $messageAutomateSettings->put($keySetting, $template);
        $settings->put('message_automate', $messageAutomateSettings);
        $user->settings = $settings;

        $user->save();
    }
}