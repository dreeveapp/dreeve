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
        $this->assertSame([], $this->settingsRepository->find(SettingsGroup::GENERAL));
    }

    public function testSaveAndFindRoundTripsANestedArray(): void
    {
        $data = [
            'appUrl' => 'https://example.com',
            'athlete' => [
                'birthday' => '1990-01-01',
                'ftpHistory' => ['2024-01-01' => 250],
            ],
        ];

        $this->settingsRepository->save(SettingsGroup::GENERAL, $data);

        $this->assertSame($data, $this->settingsRepository->find(SettingsGroup::GENERAL));
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->settingsRepository = $this->getContainer()->get(SettingsRepository::class);
    }
}
