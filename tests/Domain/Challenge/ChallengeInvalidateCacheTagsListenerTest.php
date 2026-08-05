<?php

namespace App\Tests\Domain\Challenge;

use App\Domain\Challenge\Challenge;
use App\Domain\Challenge\ChallengeId;
use App\Domain\Challenge\ChallengeRepository;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\CacheTag;
use App\Infrastructure\Cache\CacheTags;
use App\Infrastructure\Cache\Render;
use App\Infrastructure\Cache\RenderCache;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;

class ChallengeInvalidateCacheTagsListenerTest extends ContainerTestCase
{
    private ChallengeRepository $challengeRepository;
    private RenderCache $renderCache;

    public function testItInvalidatesWhenAChallengeIsImported(): void
    {
        $this->warmUpChallengesRenderCache();

        $this->challengeRepository->add(Challenge::create(
            challengeId: ChallengeId::fromUnprefixed('imported'),
            createdOn: SerializableDateTime::fromString('2023-10-10'),
            name: 'Challenge',
            logoUrl: null,
            slug: 'challenge',
        ));

        $this->assertFalse($this->renderChallenges()->wasServedFromCache());
    }

    public function testItDoesNotInvalidateWhenAChallengeIsMerelyHydratedAndStored(): void
    {
        $this->warmUpChallengesRenderCache();

        $this->challengeRepository->add(ChallengeBuilder::fromDefaults()->build());

        $this->assertTrue($this->renderChallenges()->wasServedFromCache());
    }

    private function warmUpChallengesRenderCache(): void
    {
        $this->renderChallenges();
        $this->assertTrue($this->renderChallenges()->wasServedFromCache());
    }

    private function renderChallenges(): Render
    {
        return $this->renderCache->get(
            cacheKey: 'challenges',
            cacheability: Cacheability::for('stub', CacheTags::of(CacheTag::CHALLENGES)),
            callback: fn (): string => 'rendered',
        );
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->challengeRepository = $this->getContainer()->get(ChallengeRepository::class);
        $this->renderCache = $this->getContainer()->get(RenderCache::class);
        $this->renderCache->clear();
    }
}
