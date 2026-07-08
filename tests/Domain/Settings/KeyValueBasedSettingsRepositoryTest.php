<?php

declare(strict_types=1);

namespace App\Tests\Domain\Settings;

use App\Domain\Settings\SettingsGroup;
use App\Domain\Settings\SettingsRepository;
use App\Tests\ContainerTestCase;

class KeyValueBasedSettingsRepositoryTest extends ContainerTestCase
{
    private SettingsRepository $settingsRepository;

    public function testFindReturnsEmptyArrayWhenAbsent(): void
    {
        // DAEMON is not part of the seeded settings baseline (see ProvideSettings).
        $this->assertSame([], $this->settingsRepository->find(SettingsGroup::DAEMON));
    }

    public function testSaveAndFindRoundTripsANestedArray(): void
    {
        $data = [
            'level' => 42,
            'nested' => ['foo' => 'bar', 'list' => [1, 2, 3]],
        ];

        $this->settingsRepository->save(SettingsGroup::DAEMON, $data);

        $this->assertSame($data, $this->settingsRepository->find(SettingsGroup::DAEMON));
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->settingsRepository = $this->getContainer()->get(SettingsRepository::class);
    }
}
