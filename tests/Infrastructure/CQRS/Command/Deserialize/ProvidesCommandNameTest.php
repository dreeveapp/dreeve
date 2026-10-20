<?php

namespace App\Tests\Infrastructure\CQRS\Command\Deserialize;

use App\Domain\Activity\DeleteActivities\DeleteActivities;
use App\Domain\Gear\AddGear\AddGear;
use App\Domain\Gear\Maintenance\UpdateGearMaintenanceConfig\UpdateGearMaintenanceConfig;
use App\Domain\Import\UploadActivityFile\UploadActivityFile;
use PHPUnit\Framework\TestCase;

class ProvidesCommandNameTest extends TestCase
{
    /**
     * These literals are wire identifiers (sent by the admin JS as "commandName").
     * They must stay byte-for-byte stable, so this guards the reflection-derived value
     * against the names that used to be hard-coded constants.
     *
     * @param class-string<\App\Infrastructure\CQRS\Command\Deserialize\DeserializableCommand> $command
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('provideCommands')]
    public function testItDerivesTheCommandName(string $command, string $expectedName): void
    {
        $this->assertSame($expectedName, $command::getCommandName());
    }

    /**
     * @return iterable<string, array{class-string<\App\Infrastructure\CQRS\Command\Deserialize\DeserializableCommand>, string}>
     */
    public static function provideCommands(): iterable
    {
        yield 'single word boundary' => [DeleteActivities::class, 'delete-activities'];
        yield 'two words' => [AddGear::class, 'add-gear'];
        yield 'many words' => [UpdateGearMaintenanceConfig::class, 'update-gear-maintenance-config'];
        yield 'previously without constant' => [UploadActivityFile::class, 'upload-activity-file'];
    }
}
