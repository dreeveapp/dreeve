<?php

declare(strict_types=1);

namespace App\Domain\Automation\Action\ConfiguredAction;

use App\Domain\Automation\Action\ActionType;
use App\Domain\Automation\RuleConfiguration;

interface ConfiguredAction extends \JsonSerializable
{
    public function getType(): ActionType;

    public function getConfiguration(): RuleConfiguration;
}
