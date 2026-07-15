<?php

declare(strict_types=1);

namespace App\Domain\Automation\Action\ConfiguredAction;

use App\Domain\Automation\Action\ActionType;
use App\Domain\Automation\RuleConfiguration;

final readonly class ConfiguredAction implements \JsonSerializable
{
    public function __construct(
        private ActionType $type,
        private RuleConfiguration $configuration,
    ) {
    }

    public function getType(): ActionType
    {
        return $this->type;
    }

    public function getConfiguration(): RuleConfiguration
    {
        return $this->configuration;
    }

    /**
     * @return array{type: string, config: RuleConfiguration}
     */
    public function jsonSerialize(): array
    {
        return [
            'type' => $this->type->value,
            'config' => $this->configuration,
        ];
    }
}
