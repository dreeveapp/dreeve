<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Daemon\Cron;

use App\Domain\Import\ImportMode;
use App\Infrastructure\Config\ManagedMode;
use App\Infrastructure\Daemon\Cron\CronAction;
use App\Infrastructure\Daemon\Cron\CronActionId;
use Cron\CronExpression;
use PHPUnit\Framework\TestCase;

class CronActionTest extends TestCase
{
    public function testGetIdAndExpression(): void
    {
        $cronAction = CronAction::create(
            id: CronActionId::RUN_STRAVA_IMPORT,
            expression: new CronExpression('0 2 * * *'),
        );

        $this->assertSame(CronActionId::RUN_STRAVA_IMPORT, $cronAction->getId());
        $this->assertEquals(new CronExpression('0 2 * * *'), $cronAction->getExpression());
    }

    public function testItDelegatesToItsId(): void
    {
        $cronAction = CronAction::create(
            id: CronActionId::RUN_STRAVA_IMPORT,
            expression: new CronExpression('* * * * *'),
        );

        $this->assertSame('bin/console app:import:strava', $cronAction->getCommand());
        $this->assertFalse($cronAction->supports(
            importMode: ImportMode::FILES,
            managedMode: ManagedMode::fromString('0'),
        ));
        $this->assertTrue($cronAction->supports(
            importMode: ImportMode::STRAVA_API,
            managedMode: ManagedMode::fromString('0'),
        ));
    }
}
