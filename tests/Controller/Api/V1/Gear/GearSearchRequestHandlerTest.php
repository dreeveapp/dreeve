<?php

namespace App\Tests\Controller\Api\V1\Gear;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Gear\GearId;
use App\Domain\Gear\GearRepository;
use App\Domain\Gear\GearType;
use App\Infrastructure\Measurement\Length\Kilometer;
use App\Infrastructure\Security\Api\Token;
use App\Tests\Controller\ControllerWebTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use App\Tests\Domain\Gear\GearBuilder;
use Spatie\Snapshots\MatchesSnapshots;

class GearSearchRequestHandlerTest extends ControllerWebTestCase
{
    use MatchesSnapshots;

    private Token $token;

    public function testItListsAllGear(): void
    {
        $gearRepository = static::getContainer()->get(GearRepository::class);
        $gearRepository->add(
            GearBuilder::fromDefaults()
                ->withGearId(GearId::fromUnprefixed('b12659861'))
                ->withName('Canyon Grail')
                ->withGearType(GearType::IMPORTED)
                ->build()
        );
        $gearRepository->add(
            GearBuilder::fromDefaults()
                ->withGearId(GearId::fromUnprefixed('2'))
                ->withName('Nike Pegasus')
                ->withGearType(GearType::CUSTOM)
                ->build()
        );
        $gearRepository->add(
            GearBuilder::fromDefaults()
                ->withGearId(GearId::fromUnprefixed('3'))
                ->withName('Old trainers')
                ->withGearType(GearType::CUSTOM)
                ->withIsRetired(true)
                ->build()
        );

        static::getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            activity: ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('1'))
                ->withGearId(GearId::fromUnprefixed('2'))
                ->withDistance(Kilometer::from(10.5))
                ->build(),
            rawData: [],
        ));

        $this->client->request('GET', '/api/v1/gear', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$this->token]);

        $this->assertResponseIsSuccessful();
        $this->assertMatchesJsonSnapshot((string) $this->client->getResponse()->getContent());
    }

    #[\Override]
    protected function shouldSeedActivity(): bool
    {
        return false;
    }

    #[\Override]
    protected function prepareEnvironment(): void
    {
        $this->token = Token::generate();
        $_SERVER['DREEVE_API_KEY'] = $_ENV['DREEVE_API_KEY'] = (string) $this->token;
    }
}
