<?php

namespace App\Tests\Domain\Dashboard\Widget\TrainingGoals;

use App\Domain\Dashboard\Widget\TrainingGoals\TrainingGoalType;
use App\Tests\ContainerTestCase;
use Spatie\Snapshots\MatchesSnapshots;
use Symfony\Contracts\Translation\TranslatorInterface;

class TrainingGoalTypeTest extends ContainerTestCase
{
    use MatchesSnapshots;

    public function testGetTranslations(): void
    {
        $snapshot = [];
        foreach (TrainingGoalType::cases() as $trainingGoalType) {
            $snapshot[$trainingGoalType->value] = $trainingGoalType->trans($this->getContainer()->get(TranslatorInterface::class));
        }
        $this->assertMatchesJsonSnapshot($snapshot);
    }
}
