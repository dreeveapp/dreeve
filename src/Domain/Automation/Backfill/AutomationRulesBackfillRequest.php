<?php

declare(strict_types=1);

namespace App\Domain\Automation\Backfill;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityIds;
use App\Domain\Automation\AutomationRuleId;
use App\Domain\Automation\AutomationRuleIds;

final readonly class AutomationRulesBackfillRequest implements \JsonSerializable
{
    private function __construct(
        private AutomationRuleIds $automationRuleIds,
        private ActivityIds $activityIds,
    ) {
    }

    public static function fromState(AutomationRuleIds $automationRuleIds, ActivityIds $activityIds): self
    {
        return new self($automationRuleIds, $activityIds);
    }

    /**
     * @param array<mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            AutomationRuleIds::fromArray(array_map(
                AutomationRuleId::fromString(...),
                self::readIdList($data, 'automationRuleIds')
            )),
            ActivityIds::fromArray(array_map(
                ActivityId::fromString(...),
                self::readIdList($data, 'activityIds')
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
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'automationRuleIds' => $this->automationRuleIds,
            'activityIds' => $this->activityIds,
        ];
    }

    /**
     * @param array<mixed> $data
     *
     * @return list<string>
     */
    private static function readIdList(array $data, string $key): array
    {
        $ids = $data[$key] ?? null;
        if (!is_array($ids) || !array_is_list($ids)) {
            throw new \InvalidArgumentException(sprintf('A queued automation rules backfill requires a "%s" list', $key));
        }

        foreach ($ids as $id) {
            if (!is_string($id) || '' === trim($id)) {
                throw new \InvalidArgumentException(sprintf('Every "%s" entry must be a non-empty string', $key));
            }
        }

        return $ids;
    }
}
