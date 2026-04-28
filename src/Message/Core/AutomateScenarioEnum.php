<?php

namespace Module\Message\Core;

enum AutomateScenarioEnum : int
{
    case NEW_SUBSCRIPTION = 1;

    public function getKeySetting(): string
    {
        return match ($this) {
            self::NEW_SUBSCRIPTION => 'new_subscription',
        };
    }
}
