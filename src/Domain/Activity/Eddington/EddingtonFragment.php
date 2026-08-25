<?php

declare(strict_types=1);

namespace App\Domain\Activity\Eddington;

use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\Context\AuthenticatedCacheContext;
use App\Infrastructure\Cache\Context\CacheContexts;
use App\Infrastructure\Cache\Tag\CacheTags;
use App\Infrastructure\Cache\Tag\RootCacheTag;
use App\Infrastructure\Http\Fragment\Fragment;
use App\Infrastructure\Http\Fragment\FragmentType;
use App\Infrastructure\Measurement\UnitSystem;
use App\Infrastructure\Serialization\Json;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

final readonly class EddingtonFragment implements Fragment
{
    public function __construct(
        private EddingtonCalculator $eddingtonCalculator,
        private SettingsRepository $settingsRepository,
        private TranslatorInterface $translator,
        private Environment $twig,
    ) {
    }

    public function getPath(): string
    {
        return 'eddington';
    }

    public function getType(): FragmentType
    {
        return FragmentType::PAGE;
    }

    public function getCacheability(): Cacheability
    {
        return Cacheability::for(
            cacheKey: $this->getPath(),
            cacheTags: CacheTags::of(RootCacheTag::ACTIVITIES, RootCacheTag::SETTINGS_METRICS),
            cacheContexts: CacheContexts::of(AuthenticatedCacheContext::class),
        );
    }

    public function render(): string
    {
        $eddingtonCharts = [];
        $eddingtonHistoryCharts = [];
        $allEddingtons = $this->eddingtonCalculator->calculate(...UnitSystem::cases());

        foreach ($allEddingtons as $eddington) {
            $id = $eddington->getId();
            $eddingtonCharts[$id] = Json::encode(
                EddingtonChart::create(
                    eddington: $eddington,
                    unitSystem: $eddington->getUnitSystem(),
                    translator: $this->translator,
                )->build()
            );
            $eddingtonHistoryCharts[$id] = Json::encode(
                EddingtonHistoryChart::create(
                    eddington: $eddington,
                )->build()
            );
        }

        return $this->twig->load('html/eddington.html.twig')->render([
            'activeUnitSystem' => $this->settingsRepository->appearance()->getUnitSystem(),
            'eddingtons' => $allEddingtons,
            'eddingtonCharts' => $eddingtonCharts,
            'eddingtonHistoryCharts' => $eddingtonHistoryCharts,
        ]);
    }
}
