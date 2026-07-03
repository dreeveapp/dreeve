<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\Widget;

use App\Domain\Zwift\FindZwiftStatsPerWorld\FindZwiftStatsPerWorld;
use App\Infrastructure\CQRS\Query\Bus\QueryBus;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

final readonly class ZwiftStatsWidget implements Widget
{
    public function __construct(
        private TranslatorInterface $translator,
        private QueryBus $queryBus,
        private Environment $twig,
    ) {
    }

    public function getLabel(): string
    {
        return $this->translator->trans('Zwift stats');
    }

    public function getTemplateName(): string
    {
        return 'widget--zwift-stats';
    }

    public function getDefaultConfiguration(): WidgetConfiguration
    {
        return WidgetConfiguration::empty();
    }

    public function guardValidConfiguration(WidgetConfiguration $configuration): void
    {
    }

    public function render(SerializableDateTime $now, WidgetConfiguration $configuration): ?string
    {
        $findZwiftStatsPerWorldResponse = $this->queryBus->ask(new FindZwiftStatsPerWorld());
        if (!$statsPerWorld = $findZwiftStatsPerWorldResponse->getStatsPerWorld()) {
            return null;
        }

        return $this->twig->load(sprintf('html/dashboard/widget/%s.html.twig', $this->getTemplateName()))->render([
            'statsPerWorld' => $statsPerWorld,
        ]);
    }
}
