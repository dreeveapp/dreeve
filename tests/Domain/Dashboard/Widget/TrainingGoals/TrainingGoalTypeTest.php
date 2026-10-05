<?php

namespace App\Tests\Domain\Dashboard\Widget\TrainingGoals;

use App\Domain\Dashboard\Widget\TrainingGoals\TrainingGoalType;
use App\Tests\ContainerTestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class TrainingGoalTypeTest extends ContainerTestCase
{
    public function testGetTranslations(): void
    {
        $actual = [];
        foreach (TrainingGoalType::cases() as $trainingGoalType) {
            $actual[$trainingGoalType->value] = $trainingGoalType->trans($this->getContainer()->get(TranslatorInterface::class));
        }
        $this->assertEquals(
            [
                'distance' => 'Distance',
                'elevation' => 'Elevation',
                'movingTime' => 'Moving time',
                'numberOfActivities' => 'Number of activities',
                'calories' => 'Calories',
            ],
            $actual,
        );
    }
}
