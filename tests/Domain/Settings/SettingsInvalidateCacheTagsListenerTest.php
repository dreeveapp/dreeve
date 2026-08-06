<?php

namespace App\Tests\Domain\Settings;

use App\Domain\Settings\SettingsGroup;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\CacheTag;
use App\Infrastructure\Cache\CacheTags;
use App\Infrastructure\Cache\RenderCache;
use App\Tests\ContainerTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class SettingsInvalidateCacheTagsListenerTest extends ContainerTestCase
{
    private SettingsRepository $settingsRepository;
    private RenderCache $renderCache;

    #[DataProvider('provideSettingsGroups')]
    public function testItInvalidatesTheUpdatedGroup(SettingsGroup $updatedGroup): void
    {
        foreach (SettingsGroup::cases() as $group) {
            $cacheTag = CacheTag::forSettingsGroup($group);
            $this->renderCache->get(
                cacheKey: $cacheTag->value,
                cacheability: Cacheability::for('stub', CacheTags::of($cacheTag)),
                callback: fn (): string => 'rendered',
            );
        }

        $this->settingsRepository->save($updatedGroup, ['some' => 'setting']);

        $updatedGroupIsCrossCutting = in_array(
            CacheTag::forSettingsGroup($updatedGroup),
            CacheTag::crossCutting(),
            true
        );

        foreach (SettingsGroup::cases() as $group) {
            $cacheTag = CacheTag::forSettingsGroup($group);
            $this->assertEquals(
                !$updatedGroupIsCrossCutting && $group !== $updatedGroup,
                $this->renderCache->get(
                    cacheKey: $cacheTag->value,
                    cacheability: Cacheability::for('stub', CacheTags::of($cacheTag)),
                    callback: fn (): string => 'rendered',
                )->wasServedFromCache()
            );
        }
    }

    public static function provideSettingsGroups(): \Generator
    {
        foreach (SettingsGroup::cases() as $group) {
            yield $group->value => [$group];
        }
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->settingsRepository = $this->getContainer()->get(SettingsRepository::class);
        $this->renderCache = $this->getContainer()->get(RenderCache::class);
        $this->renderCache->clear();
    }
}
