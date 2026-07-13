<?php

namespace App\Tests\Domain\Challenge;

use App\Domain\Challenge\ChallengeId;
use App\Domain\Challenge\ChallengeRepository;
use App\Domain\Challenge\Challenges;
use App\Domain\Challenge\DbalChallengeRepository;
use App\Infrastructure\Exception\EntityNotFound;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;

class DbalChallengeRepositoryTest extends ContainerTestCase
{
    private ChallengeRepository $challengeRepository;

    public function testFindAndSave(): void
    {
        $challenge = ChallengeBuilder::fromDefaults()->build();
        $this->challengeRepository->add($challenge);

        $this->assertEquals(
            $challenge,
            $this->challengeRepository->find($challenge->getId())
        );
    }

    public function testItShouldThrowWhenNotFound(): void
    {
        $this->expectExceptionObject(new EntityNotFound('Challenge "challenge-1" not found'));
        $this->challengeRepository->find(ChallengeId::fromUnprefixed('1'));
    }

    public function testUpdateChallengeId(): void
    {
        $oldId = ChallengeId::fromUnprefixed('old-id');
        $newId = ChallengeId::fromUnprefixed('new-id');

        $challenge = ChallengeBuilder::fromDefaults()
            ->withChallengeId($oldId)
            ->build();
        $this->challengeRepository->add($challenge);

        $this->challengeRepository->updateChallengeId($oldId, $newId);

        $this->expectExceptionObject(new EntityNotFound('Challenge "challenge-old-id" not found'));
        $this->challengeRepository->find($oldId);
    }

    public function testUpdateChallengeIdFindsNewId(): void
    {
        $oldId = ChallengeId::fromUnprefixed('old-id');
        $newId = ChallengeId::fromUnprefixed('new-id');

        $challenge = ChallengeBuilder::fromDefaults()
            ->withChallengeId($oldId)
            ->build();
        $this->challengeRepository->add($challenge);

        $this->challengeRepository->updateChallengeId($oldId, $newId);

        $updated = $this->challengeRepository->find($newId);
        $this->assertEquals($newId, $updated->getId());
    }

    public function testFindAll(): void
    {
        $challengeOne = ChallengeBuilder::fromDefaults()
            ->withChallengeId(ChallengeId::fromUnprefixed('1'))
            ->withCreatedOn(SerializableDateTime::fromString('2023-10-10 14:00:34'))
            ->build();
        $this->challengeRepository->add($challengeOne);
        $challengeTwo = ChallengeBuilder::fromDefaults()
            ->withChallengeId(ChallengeId::fromUnprefixed('2'))
            ->withCreatedOn(SerializableDateTime::fromString('2023-10-10 15:00:34'))
            ->build();
        $this->challengeRepository->add($challengeTwo);

        $this->assertEquals(
            Challenges::fromArray([$challengeTwo, $challengeOne]),
            $this->challengeRepository->findAll()
        );
    }

    public function testCount(): void
    {
        $challengeOne = ChallengeBuilder::fromDefaults()
            ->withChallengeId(ChallengeId::fromUnprefixed('1'))
            ->withCreatedOn(SerializableDateTime::fromString('2023-10-10 14:00:34'))
            ->build();
        $this->challengeRepository->add($challengeOne);
        $challengeTwo = ChallengeBuilder::fromDefaults()
            ->withChallengeId(ChallengeId::fromUnprefixed('2'))
            ->withCreatedOn(SerializableDateTime::fromString('2023-10-10 15:00:34'))
            ->build();
        $this->challengeRepository->add($challengeTwo);

        $this->assertEquals(
            2,
            $this->challengeRepository->count()
        );
    }

    public function testDeleteWithNonAlphanumericIds(): void
    {
        $alphanumericChallenge = ChallengeBuilder::fromDefaults()
            ->withChallengeId(ChallengeId::fromUnprefixed('2022-10_valid_id'))
            ->build();
        $this->challengeRepository->add($alphanumericChallenge);

        $nonAlphanumericChallenge = ChallengeBuilder::fromDefaults()
            ->withChallengeId(ChallengeId::fromUnprefixed('2022-10_roc_d&#x27;azur'))
            ->build();
        $this->challengeRepository->add($nonAlphanumericChallenge);

        $this->challengeRepository->deleteWithNonAlphanumericIds();

        $this->assertEquals(1, $this->challengeRepository->count());
        $this->assertEquals(
            $alphanumericChallenge,
            $this->challengeRepository->find($alphanumericChallenge->getId())
        );
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->challengeRepository = new DbalChallengeRepository(
            $this->getConnection()
        );
    }
}
