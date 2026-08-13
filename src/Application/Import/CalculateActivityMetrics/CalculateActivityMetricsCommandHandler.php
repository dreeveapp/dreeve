<?php

declare(strict_types=1);

namespace App\Application\Import\CalculateActivityMetrics;

use App\Application\Import\CalculateActivityMetrics\Pipeline\CalculateActivityMetricsStep;
use App\Infrastructure\CQRS\Command\Command;
use App\Infrastructure\CQRS\Command\CommandHandler;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final readonly class CalculateActivityMetricsCommandHandler implements CommandHandler
{
    /**
     * @param iterable<CalculateActivityMetricsStep> $steps
     */
    public function __construct(
        #[AutowireIterator('app.activity_calculate_metrics.pipeline_step')]
        private iterable $steps,
    ) {
    }

    public function handle(Command $command): void
    {
        assert($command instanceof CalculateActivityMetrics);

        foreach ($this->steps as $step) {
            $step->process($command->getOutput());
        }
    }
}
