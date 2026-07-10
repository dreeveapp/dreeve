<?php

declare(strict_types=1);

namespace App\Tests\Domain\Settings;

use App\Domain\Settings\DaemonSettings;
use App\Domain\Settings\KeyValueBasedSettingsRepository;
use App\Domain\Settings\SettingsRepository;
use App\Tests\ContainerTestCase;

class KeyValueBasedSettingsRepositoryTest extends ContainerTestCase
{
    private SettingsRepository $settingsRepository;

    public function testFindReturnsEmptyArrayWhenAbsent(): void
    {
        $this->assertEquals(
            DaemonSettings::fromArray([]),
            $this->settingsRepository->daemon()
        );
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->settingsRepository = $this->getContainer()->get(KeyValueBasedSettingsRepository::class);
    }
}
