<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\Widget;

use App\Domain\Activity\Eddington\Eddington;
use App\Domain\Activity\Eddington\EddingtonCalculator;
use App\Domain\Dashboard\DashboardWidgetId;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\Cache\Tag\CacheTags;
use App\Infrastructure\Cache\Tag\RootCacheTag;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

final readonly class EddingtonWidget implements Widget
{
    public function __construct(
        private TranslatorInterface $translator,
        private EddingtonCalculator $eddingtonCalculator,
        private SettingsRepository $settingsRepository,
        private Environment $twig,
    ) {
    }

    public function getLabel(): string
    {
        return $this->translator->trans('Eddington');
    }

    public function getTemplateName(): string
    {
        return 'widget--eddington';
    }

    public function getCacheTags(): CacheTags
    {
        return CacheTags::of(RootCacheTag::ACTIVITIES, RootCacheTag::SETTINGS_METRICS);
    }

    public function getDefaultConfiguration(): WidgetConfiguration
    {
        return WidgetConfiguration::empty();
    }

    public function guardValidConfiguration(WidgetConfiguration $configuration): void
    {
    }

    public function render(DashboardWidgetId $dashboardWidgetId, SerializableDateTime $now, WidgetConfiguration $configuration): ?string
    {
        $eddingtons = array_filter(
            $this->eddingtonCalculator->calculate($this->settingsRepository->appearance()->getUnitSystem()),
            static fn (Eddington $eddington): bool => $eddington->getConfig()->showInDashboardWidget()
        );

        if ([] === $eddingtons) {
            return null;
        }

        return $this->twig->load(sprintf('html/dashboard/widget/%s.html.twig', $this->getTemplateName()))->render([
            'eddingtons' => $eddingtons,
        ]);
    }
}
