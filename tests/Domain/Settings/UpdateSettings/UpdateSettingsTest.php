<?php

declare(strict_types=1);

namespace App\Tests\Domain\Settings\UpdateSettings;

use App\Domain\Settings\UpdateSettings\UpdateSettings;
use App\Infrastructure\CQRS\Command\Deserialize\CouldNotDeserializeCommand;
use PHPUnit\Framework\TestCase;

class UpdateSettingsTest extends TestCase
{
    public function testItThrowsWhenGroupIsMissing(): void
    {
        $this->expectException(CouldNotDeserializeCommand::class);
        $this->expectExceptionMessage('A valid "group" is required.');

        UpdateSettings::fromPayload(['data' => []]);
    }

    public function testItThrowsWhenGroupIsUnknown(): void
    {
        $this->expectException(CouldNotDeserializeCommand::class);
        $this->expectExceptionMessage('A valid "group" is required.');

        UpdateSettings::fromPayload(['group' => 'does-not-exist', 'data' => []]);
    }

    public function testItThrowsWhenDataIsNotAnArray(): void
    {
        $this->expectException(CouldNotDeserializeCommand::class);
        $this->expectExceptionMessage('"data" must be an object.');

        UpdateSettings::fromPayload(['group' => 'general', 'data' => 'not-an-array']);
    }

    public function testItThrowsWhenGroupIsNotYetMigrated(): void
    {
        $this->expectException(CouldNotDeserializeCommand::class);
        $this->expectExceptionMessage('Settings group "zwift" is not migrated yet.');

        UpdateSettings::fromPayload(['group' => 'zwift', 'data' => []]);
    }
}
