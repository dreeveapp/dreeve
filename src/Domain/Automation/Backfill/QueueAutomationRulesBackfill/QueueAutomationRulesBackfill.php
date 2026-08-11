<?php

declare(strict_types=1);

namespace App\Domain\Automation\Backfill\QueueAutomationRulesBackfill;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityIds;
use App\Domain\Automation\AutomationRuleId;
use App\Domain\Automation\AutomationRuleIds;
use App\Infrastructure\CQRS\Command\Deserialize\CouldNotDeserializeCommand;
use App\Infrastructure\CQRS\Command\Deserialize\DeserializableCommand;
use App\Infrastructure\CQRS\Command\Deserialize\ProvidesCommandName;
use App\Infrastructure\CQRS\Command\DomainCommand;
use App\Infrastructure\CQRS\Command\SuppressesFlashMessage;

#[SuppressesFlashMessage]
final readonly class QueueAutomationRulesBackfill extends DomainCommand implements DeserializableCommand
{
    use ProvidesCommandName;

    private function __construct(
        private AutomationRuleIds $automationRuleIds,
        private ActivityIds $activityIds,
    ) {
    }

    public static function fromPayload(array $payload): self
    {
        return new self(
            AutomationRuleIds::fromArray(array_map(
                AutomationRuleId::fromPrefixedOrUnprefixed(...),
                self::parseIdList($payload, 'automationRuleIds')
            )),
            ActivityIds::fromArray(array_map(
                ActivityId::fromPrefixedOrUnprefixed(...),
                self::parseIdList($payload, 'activityIds')
            )),
        );
    }

    public function getAutomationRuleIds(): AutomationRuleIds
    {
        return $this->automationRuleIds;
    }

    public function getActivityIds(): ActivityIds
    {
        return $this->activityIds;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return list<string>
     */
    private static function parseIdList(array $payload, string $key): array
    {
        $ids = $payload[$key] ?? null;
        if (!is_array($ids) || [] === $ids || !array_is_list($ids)) {
            throw CouldNotDeserializeCommand::invalidPayload(sprintf('A non-empty "%s" list is required.', $key));
        }

        $parsed = [];
        foreach ($ids as $id) {
            if (!is_string($id) || '' === trim($id)) {
                throw CouldNotDeserializeCommand::invalidPayload(sprintf('Each "%s" entry must be a non-empty string.', $key));
            }
            $parsed[] = trim($id);
        }

        return $parsed;
    }
}
