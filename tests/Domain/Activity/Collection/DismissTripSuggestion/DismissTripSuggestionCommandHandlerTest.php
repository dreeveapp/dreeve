<?php

declare(strict_types=1);

namespace App\Tests\Domain\Activity\Collection\DismissTripSuggestion;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityIds;
use App\Domain\Activity\Collection\DismissedTripSuggestionRepository;
use App\Domain\Activity\Collection\DismissTripSuggestion\DismissTripSuggestion;
use App\Infrastructure\CQRS\Command\Bus\CommandBus;
use App\Infrastructure\CQRS\Command\Deserialize\CouldNotDeserializeCommand;
use App\Tests\ContainerTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class DismissTripSuggestionCommandHandlerTest extends ContainerTestCase
{
    public function testHandle(): void
    {
        $dismissedTripSuggestionRepository = $this->getContainer()->get(DismissedTripSuggestionRepository::class);
        $this->assertEquals(ActivityIds::empty(), $dismissedTripSuggestionRepository->findActivityIds());

        $commandBus = $this->getContainer()->get(CommandBus::class);
        $commandBus->dispatch(DismissTripSuggestion::fromPayload([
            'activityIds' => ['activity-1', 'activity-2'],
        ]));
        $commandBus->dispatch(DismissTripSuggestion::fromPayload([
            'activityIds' => ['activity-2', 'activity-3'],
        ]));

        $this->assertEquals(
            ActivityIds::fromArray([
                ActivityId::fromUnprefixed('1'),
                ActivityId::fromUnprefixed('2'),
                ActivityId::fromUnprefixed('3'),
            ]),
            $dismissedTripSuggestionRepository->findActivityIds(),
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    #[DataProvider('provideInvalidPayloads')]
    public function testFromPayloadThrowsOnInvalidPayload(array $payload, string $expectedExceptionMessage): void
    {
        $this->expectExceptionObject(CouldNotDeserializeCommand::invalidPayload($expectedExceptionMessage));

        DismissTripSuggestion::fromPayload($payload);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function provideInvalidPayloads(): iterable
    {
        yield 'missing activity ids' => [
            [],
            'The "activityIds" must be a non-empty list.',
        ];

        yield 'empty activity ids' => [
            ['activityIds' => []],
            'The "activityIds" must be a non-empty list.',
        ];

        yield 'invalid activity id' => [
            ['activityIds' => ['1']],
            'The "activityIds" contain an invalid activity id.',
        ];
    }
}
