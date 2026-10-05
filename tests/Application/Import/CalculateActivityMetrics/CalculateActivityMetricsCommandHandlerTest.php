<?php

namespace App\Tests\Application\Import\CalculateActivityMetrics;

use App\Application\Import\CalculateActivityMetrics\CalculateActivityMetrics;
use App\Infrastructure\CQRS\Command\Bus\CommandBus;
use App\Tests\ContainerTestCase;
use App\Tests\SpyOutput;

class CalculateActivityMetricsCommandHandlerTest extends ContainerTestCase
{
    private CommandBus $commandBus;

    public function testHandle(): void
    {
        $output = new SpyOutput();

        $this->commandBus->dispatch(new CalculateActivityMetrics($output));
        $this->assertSame('', (string) $output);
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->commandBus = $this->getContainer()->get(CommandBus::class);
    }
}
