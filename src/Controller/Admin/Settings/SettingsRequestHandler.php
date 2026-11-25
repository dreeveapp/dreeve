<?php

declare(strict_types=1);

namespace App\Controller\Admin\Settings;

use App\Application\AppUrl;
use App\Domain\Athlete\HeartRateZone\HeartRateZoneConfiguration;
use App\Domain\Athlete\Weight\AthleteWeightHistory;
use App\Domain\Settings\DbalSettingsRepository;
use App\Domain\Settings\SettingsGroup;
use App\Domain\Settings\SettingsName;
use App\Domain\Settings\SettingsRepository;
use App\Domain\Settings\UpdateAthleteSettings\UpdateAthleteSettings;
use App\Domain\Settings\UpdateSettings\UpdateSettings;
use App\Infrastructure\Http\Response\HtmlResponse;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

#[AsController]
final readonly class SettingsRequestHandler
{
    public function __construct(
        private Environment $twig,
        #[Autowire(service: DbalSettingsRepository::class)]
        private SettingsRepository $settingsRepository,
        private AppUrl $appUrl,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    #[Route(path: '/admin/settings', name: 'admin_settings_index', methods: ['GET'], priority: 10)]
    public function index(): RedirectResponse
    {
        return new RedirectResponse($this->urlGenerator->generate('admin_settings', [
            'group' => SettingsGroup::GENERAL->value,
        ]));
    }

    #[Route(path: '/admin/settings/athlete', name: 'admin_settings_athlete', methods: ['GET'], priority: 10)]
    public function athlete(): HtmlResponse
    {
        return new HtmlResponse($this->twig->render(
            'html/admin/page/settings/athlete.html.twig',
            [
                'dispatchCommand' => UpdateAthleteSettings::getCommandName(),
                'settings' => $this->settingsRepository->findGroup(SettingsGroup::GENERAL),
            ],
        ));
    }

    #[Route(path: '/admin/settings/{group}', name: 'admin_settings', methods: ['GET'], priority: 5)]
    public function handle(string $group): HtmlResponse
    {
        $settingsGroup = SettingsGroup::fromSlug($group)
            ?? throw new NotFoundHttpException(sprintf('Unknown settings group "%s"', $group));

        return new HtmlResponse($this->twig->render(
            sprintf('html/admin/page/settings/%s.html.twig', $settingsGroup->getSlug()),
            [
                'dispatchCommand' => UpdateSettings::getCommandName(),
                'group' => $settingsGroup,
                'settings' => $this->settingsRepository->findGroup($settingsGroup),
                'appUrl' => $this->appUrl,
                'sportTypes' => $this->settingsRepository->appearance()->getSportTypesSortingOrder(),
                'defaultHeartRateZones' => HeartRateZoneConfiguration::getDefaultZones(),
                'weightHistoryRows' => SettingsGroup::ATHLETE_WEIGHT === $settingsGroup ? $this->weightHistoryRows() : [],
            ],
        ));
    }

    /** @return list<array{on: string, weight: float, source: string}> */
    private function weightHistoryRows(): array
    {
        $unitSystem = $this->settingsRepository->appearance()->getUnitSystem();
        $histories = [
            'manual' => AthleteWeightHistory::fromArray($this->settingsRepository->find(SettingsName::WEIGHT_HISTORY) ?? [], $unitSystem),
            'garmin' => $this->settingsRepository->athleteWeight()->getGarminHistory($unitSystem),
        ];
        $rows = [];
        foreach ($histories as $source => $history) {
            foreach ($history->findAll() as $weighIn) {
                $rows[] = [
                    'on' => $weighIn->getOn()->format('Y-m-d'),
                    'weight' => $weighIn->getWeight()->toFloat(),
                    'source' => $source,
                ];
            }
        }
        usort($rows, static fn (array $a, array $b): int => strcmp($b['on'], $a['on']) ?: strcmp($b['source'], $a['source']));

        return $rows;
    }
}
