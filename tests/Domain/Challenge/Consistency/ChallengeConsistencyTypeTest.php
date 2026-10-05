<?php

namespace App\Tests\Domain\Challenge\Consistency;

use App\Domain\Challenge\Consistency\ChallengeConsistencyType;
use App\Tests\ContainerTestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class ChallengeConsistencyTypeTest extends ContainerTestCase
{
    public function testGetTranslations(): void
    {
        $actual = [];
        foreach (ChallengeConsistencyType::cases() as $challengeConsistencyType) {
            $actual[$challengeConsistencyType->value] = $challengeConsistencyType->trans($this->getContainer()->get(TranslatorInterface::class));
        }
        $this->assertEquals(
            [
                'distance' => 'Distance',
                'distanceInOneActivity' => 'Distance (single activity)',
                'elevation' => 'Elevation',
                'elevationInOneActivity' => 'Elevation (single activity)',
                'movingTime' => 'Moving time',
                'numberOfActivities' => 'Number of activities',
                'calories' => 'Calories',
            ],
            $actual,
        );
    }
}
