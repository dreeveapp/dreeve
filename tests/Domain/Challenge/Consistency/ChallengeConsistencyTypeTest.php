<?php

namespace App\Tests\Domain\Challenge\Consistency;

use App\Domain\Challenge\Consistency\ChallengeConsistencyType;
use App\Tests\ContainerTestCase;
use Spatie\Snapshots\MatchesSnapshots;
use Symfony\Contracts\Translation\TranslatorInterface;

class ChallengeConsistencyTypeTest extends ContainerTestCase
{
    use MatchesSnapshots;

    public function testGetTranslations(): void
    {
        $snapshot = [];
        foreach (ChallengeConsistencyType::cases() as $challengeConsistencyType) {
            $snapshot[$challengeConsistencyType->value] = $challengeConsistencyType->trans($this->getContainer()->get(TranslatorInterface::class));
        }
        $this->assertMatchesJsonSnapshot($snapshot);
    }
}
