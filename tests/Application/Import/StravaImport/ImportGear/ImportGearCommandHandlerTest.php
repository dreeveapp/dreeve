<?php

namespace App\Tests\Application\Import\StravaImport\ImportGear;

use App\Application\Import\StravaImport\ImportGear\ImportGear;
use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityIds;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Gear\GearId;
use App\Domain\Gear\GearRepository;
use App\Domain\Strava\Strava;
use App\Infrastructure\CQRS\Command\Bus\CommandBus;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use App\Tests\Domain\Gear\GearBuilder;
use App\Tests\Domain\Strava\SpyStrava;
use App\Tests\SpyOutput;
use PHPUnit\Framework\Attributes\DataProvider;

class ImportGearCommandHandlerTest extends ContainerTestCase
{
    private CommandBus $commandBus;
    private SpyStrava $strava;

    /**
     * @param list<string>|null $restrictToActivityIds
     * @param list<string>      $expectedOutput
     */
    #[DataProvider('provideImports')]
    public function testHandle(int $maxNumberOfCallsBeforeTriggering429, bool $throwException, ?array $restrictToActivityIds, array $expectedOutput): void
    {
        $output = new SpyOutput();
        $this->strava->setMaxNumberOfCallsBeforeTriggering429($maxNumberOfCallsBeforeTriggering429);
        if ($throwException) {
            $this->strava->triggerExceptionOnNextCall();
        }

        $this->getContainer()->get(GearRepository::class)->add(
            GearBuilder::fromDefaults()
                ->withGearId(GearId::fromUnprefixed('b12659861'))
                ->build()
        );
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('1'))
                ->withGearId(GearId::fromUnprefixed('b12659861'))
                ->build(),
            ['gear_id' => 'b12659861']
        ));
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('2'))
                ->withGearId(GearId::fromUnprefixed('b12659743'))
                ->build(),
            ['gear_id' => 'b12659743']
        ));
        $this->getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('3'))
                ->withGearId(GearId::fromUnprefixed('b12659792'))
                ->build(),
            ['gear_id' => 'b12659792']
        ));

        $this->commandBus->dispatch(new ImportGear(
            $output,
            null === $restrictToActivityIds ? null : ActivityIds::fromArray(array_map(ActivityId::fromUnprefixed(...), $restrictToActivityIds)),
        ));

        $this->assertSame(implode("\n", $expectedOutput), (string) $output);
    }

    public static function provideImports(): iterable
    {
        yield 'all gear' => [10000, false, null, [
            'Importing gear...',
            '  => Imported gear "Retro Race Bike"',
            '  => Imported gear "Zwift Hub"',
            '  => Imported gear "Elite Direto XR-T ☠️"',
        ]];
        yield 'restricted to activity ids' => [10000, false, ['1'], [
            'Importing gear...',
            '  => Imported gear "Retro Race Bike"',
        ]];
        yield 'too many requests' => [3, false, null, [
            'Importing gear...',
            '  => Imported gear "Retro Race Bike"',
            '  => Imported gear "Zwift Hub"',
            '<error>You reached the daily Strava API rate limit. You will need to import the rest of your data tomorrow</error>',
        ]];
        yield 'unexpected error' => [1000, true, null, [
            'Importing gear...',
            '<error>Strava API threw error: The error</error>',
        ]];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->commandBus = $this->getContainer()->get(CommandBus::class);
        $this->strava = $this->getContainer()->get(Strava::class);
    }
}
