<?php

declare(strict_types=1);

namespace App\Application\Build\BuildHeatmapHtml;

use App\Domain\Activity\Route\Route;
use App\Domain\Activity\Route\RouteRepository;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Activity\SportType\SportTypeRepository;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\CQRS\Command\Command;
use App\Infrastructure\CQRS\Command\CommandHandler;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\Twig\UrlTwigExtension;
use League\Flysystem\FilesystemOperator;
use Twig\Environment;

final readonly class BuildHeatmapHtmlCommandHandler implements CommandHandler
{
    public function __construct(
        private RouteRepository $routeRepository,
        private SportTypeRepository $sportTypeRepository,
        private SettingsRepository $settingsRepository,
        private Environment $twig,
        private UrlTwigExtension $urlTwigExtension,
        private FilesystemOperator $buildHtmlStorage,
        private FilesystemOperator $buildApiStorage,
    ) {
    }

    public function handle(Command $command): void
    {
        assert($command instanceof BuildHeatmapHtml);

        $appearance = $this->settingsRepository->appearance();

        $importedSportTypes = $this->sportTypeRepository->findAll();
        $routes = $this->routeRepository->findAll();

        $enrichedRoutes = [];
        foreach ($routes as $route) {
            $enrichedRoutes[] = $route
                ->withUnitSystemAndDateTimeFormat(
                    unitSystem: $appearance->getUnitSystem(),
                    dateAndTimeFormat: $appearance->getDateAndTimeFormat(),
                )
                ->withRelativeActivityUri($this->urlTwigExtension->toRelativeUrl('activity/'.$route->getActivityId().'.html'));
        }

        $this->buildApiStorage->write(
            'heatmap/routes.json',
            (string) Json::encodeAndCompress($enrichedRoutes),
        );

        $this->buildHtmlStorage->write(
            'heatmap.html',
            $this->twig->load('html/heatmap.html.twig')->render([
                'numberOfRoutes' => count($enrichedRoutes),
                'sportTypes' => $importedSportTypes->filter(
                    fn (SportType $sportType): bool => $sportType->supportsReverseGeocoding()
                ),
                'numberOfCountriesWithWorkouts' => count(array_filter(array_unique($routes->map(
                    fn (Route $route): ?string => $route->getRouteGeography()->getStartingPointCountryCode()
                )))),
                'heatmapConfig' => $appearance->getHeatmapConfig(),
            ]),
        );
    }
}
