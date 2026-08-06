<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Doctrine\Migrations;

use App\Infrastructure\Doctrine\Migrations\DatabaseSchemaCheckConsoleSubscriber;
use App\Infrastructure\Doctrine\Migrations\RequiresUpToDateDatabaseSchema;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\BufferedOutput;

#[RequiresUpToDateDatabaseSchema]
final class TaggedCommand extends Command
{
}

final class UntaggedCommand extends Command
{
}

class DatabaseSchemaCheckConsoleSubscriberTest extends TestCase
{
    private VoidMigrationRunner $migrationRunner;
    private DatabaseSchemaCheckConsoleSubscriber $subscriber;

    public function testItBlocksTaggedCommandWhenSchemaIsOutOfDate(): void
    {
        $this->migrationRunner->markAsNotAtLatestVersion();

        $output = new BufferedOutput();
        $event = new ConsoleCommandEvent(new TaggedCommand('app:tagged'), new StringInput(''), $output);

        $this->subscriber->onConsoleCommand($event);

        $this->assertFalse($event->commandShouldRun());
        $this->assertStringContainsString('Your database is not up to date with the migration schema', $output->fetch());
    }

    public function testItAllowsTaggedCommandWhenSchemaIsUpToDate(): void
    {
        $output = new BufferedOutput();
        $event = new ConsoleCommandEvent(new TaggedCommand('app:tagged'), new StringInput(''), $output);

        $this->subscriber->onConsoleCommand($event);

        $this->assertTrue($event->commandShouldRun());
        $this->assertSame('', $output->fetch());
    }

    public function testItNeverChecksUntaggedCommand(): void
    {
        $this->migrationRunner->markAsNotAtLatestVersion();

        $output = new BufferedOutput();
        $event = new ConsoleCommandEvent(new UntaggedCommand('app:untagged'), new StringInput(''), $output);

        $this->subscriber->onConsoleCommand($event);

        $this->assertTrue($event->commandShouldRun());
        $this->assertSame('', $output->fetch());
    }

    public function testItBlocksTaggedCommandWhenConnectionFails(): void
    {
        $this->migrationRunner->throwOnNextRun();

        $output = new BufferedOutput();
        $event = new ConsoleCommandEvent(new TaggedCommand('app:tagged'), new StringInput(''), $output);

        $this->subscriber->onConsoleCommand($event);

        $this->assertFalse($event->commandShouldRun());
        $this->assertStringContainsString('Your database is not up to date with the migration schema', $output->fetch());
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->subscriber = new DatabaseSchemaCheckConsoleSubscriber(
            $this->migrationRunner = new VoidMigrationRunner(),
        );
    }
}
