<?php

declare(strict_types=1);

namespace App\Tests\Domain\Automation\Backfill\QueueAutomationRulesBackfill;

use App\Domain\Automation\Backfill\AutomationRulesBackfillQueue;
use App\Domain\Automation\Backfill\QueueAutomationRulesBackfill\QueueAutomationRulesBackfill;
use App\Domain\Automation\Backfill\QueueAutomationRulesBackfill\QueueAutomationRulesBackfillCommandHandler;
use App\Infrastructure\CQRS\Command\Deserialize\CouldNotDeserializeCommand;
use App\Infrastructure\KeyValue\DbalKeyValueStore;
use App\Infrastructure\KeyValue\Key;
use App\Tests\ContainerTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class QueueAutomationRulesBackfillCommandHandlerTest extends ContainerTestCase
{
    private DbalKeyValueStore $keyValueStore;
    private AutomationRulesBackfillQueue $queue;
    private QueueAutomationRulesBackfillCommandHandler $handler;

    public function testHandleQueuesTheSelection(): void
    {
        $this->assertFalse($this->queue->isQueued());

        $this->handler->handle(QueueAutomationRulesBackfill::fromPayload([
            'automationRuleIds' => ['automationRule-1', 'automationRule-2'],
            'activityIds' => ['activity-a', 'activity-b'],
        ]));

        $this->assertTrue($this->queue->isQueued());
        $request = $this->queue->find();
        $this->assertSame(['automationRule-1', 'automationRule-2'], array_map(strval(...), $request->getAutomationRuleIds()->toArray()));
        $this->assertSame(['activity-a', 'activity-b'], array_map(strval(...), $request->getActivityIds()->toArray()));

        $this->handler->handle(QueueAutomationRulesBackfill::fromPayload([
            'automationRuleIds' => ['3'],
            'activityIds' => ['c'],
        ]));

        $request = $this->queue->find();
        $this->assertSame(['automationRule-3'], array_map(strval(...), $request->getAutomationRuleIds()->toArray()));
        $this->assertSame(['activity-c'], array_map(strval(...), $request->getActivityIds()->toArray()));
        $this->assertSame(
            1,
            $this->getConnection()->fetchOne('SELECT COUNT(*) FROM KeyValue WHERE `key` = :key', ['key' => Key::AUTOMATION_RULES_BACKFILL->value])
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    #[DataProvider('provideInvalidPayloads')]
    public function testFromPayloadRejects(array $payload, string $expectedMessage): void
    {
        $this->expectExceptionObject(CouldNotDeserializeCommand::invalidPayload($expectedMessage));

        QueueAutomationRulesBackfill::fromPayload($payload);
    }

    public static function provideInvalidPayloads(): iterable
    {
        yield 'no rules' => [
            ['activityIds' => ['activity-a']],
            'A non-empty "automationRuleIds" list is required.',
        ];
        yield 'no activities' => [
            ['automationRuleIds' => ['automationRule-1']],
            'A non-empty "activityIds" list is required.',
        ];
        yield 'empty rule list' => [
            ['automationRuleIds' => [], 'activityIds' => ['activity-a']],
            'A non-empty "automationRuleIds" list is required.',
        ];
        yield 'rules not a list' => [
            ['automationRuleIds' => ['key' => 'automationRule-1'], 'activityIds' => ['activity-a']],
            'A non-empty "automationRuleIds" list is required.',
        ];
        yield 'blank activity id' => [
            ['automationRuleIds' => ['automationRule-1'], 'activityIds' => ['  ']],
            'Each "activityIds" entry must be a non-empty string.',
        ];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->keyValueStore = new DbalKeyValueStore($this->getConnection());
        $this->queue = new AutomationRulesBackfillQueue($this->keyValueStore);
        $this->handler = new QueueAutomationRulesBackfillCommandHandler($this->queue);
    }
}
