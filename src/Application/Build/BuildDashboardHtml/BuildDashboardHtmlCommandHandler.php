<?php

declare(strict_types=1);

namespace App\Application\Build\BuildDashboardHtml;

use App\Domain\Dashboard\RenderedWidgets;
use App\Infrastructure\CQRS\Command\Command;
use App\Infrastructure\CQRS\Command\CommandHandler;
use League\Flysystem\FilesystemOperator;
use Twig\Environment;

final readonly class BuildDashboardHtmlCommandHandler implements CommandHandler
{
    public function __construct(
        private RenderedWidgets $renderedWidgets,
        private Environment $twig,
        private FilesystemOperator $buildHtmlStorage,
    ) {
    }

    public function handle(Command $command): void
    {
        assert($command instanceof BuildDashboardHtml);

        $this->buildHtmlStorage->write(
            'dashboard.html',
            $this->twig->load('html/dashboard/dashboard.html.twig')->render([
                'widgets' => $this->renderedWidgets,
            ]),
        );
    }
}
