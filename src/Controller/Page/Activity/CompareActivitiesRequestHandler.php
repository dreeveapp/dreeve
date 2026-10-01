<?php

declare(strict_types=1);

namespace App\Controller\Page\Activity;

use App\Application\AppShell;
use App\Application\Navigation\NavigationSection;
use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityIds;
use App\Domain\Activity\Comparison\ComparedActivities;
use App\Domain\Activity\Comparison\ComparedActivity;
use App\Domain\Activity\Comparison\ComparisonDataset;
use App\Domain\Activity\EnrichedActivityRepository;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\Http\PrivateNoStoreHtmlResponse;
use App\Infrastructure\Serialization\Json;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

#[AsController]
final readonly class CompareActivitiesRequestHandler
{
    public function __construct(
        private EnrichedActivityRepository $enrichedActivityRepository,
        private SettingsRepository $settingsRepository,
        private UrlGeneratorInterface $urlGenerator,
        private TranslatorInterface $translator,
        private Environment $twig,
        private AppShell $appShell,
    ) {
    }

    #[Route(path: '/activities/compare', name: 'activities_compare', methods: ['GET'])]
    public function handle(Request $request): PrivateNoStoreHtmlResponse
    {
        $activityIds = ActivityIds::empty();
        foreach (explode(',', (string) $request->query->get('activities', '')) as $activityId) {
            try {
                $activityIds->add(ActivityId::fromString(trim($activityId)));
            } catch (\InvalidArgumentException) {
            }
        }

        return new PrivateNoStoreHtmlResponse($this->appShell->render(
            content: $this->renderFor($activityIds),
            navigationSection: NavigationSection::ACTIVITIES,
            openGraph: null,
        ));
    }

    private function renderFor(ActivityIds $activityIds): string
    {
        $comparedActivities = ComparedActivities::fromEnrichedActivities(
            $this->enrichedActivityRepository->findByIds($activityIds)
        );
        $selectedActivityIds = $comparedActivities->map(
            fn (ComparedActivity $comparedActivity): string => (string) $comparedActivity->getActivityId()
        );
        $compareUrl = fn (array $activityIds): string => $this->urlGenerator->generate('activities_compare')
            .([] === $activityIds ? '' : '?activities='.implode(',', $activityIds));

        return $this->twig->load('html/activity/compare.html.twig')->render([
            'rows' => $comparedActivities->map(fn (ComparedActivity $comparedActivity): array => [
                'activity' => $comparedActivity,
                'removeUrl' => $compareUrl(array_values(array_diff($selectedActivityIds, [(string) $comparedActivity->getActivityId()]))),
            ]),
            'addUrlTemplate' => $compareUrl([...$selectedActivityIds, '{value}']),
            'dataset' => count($comparedActivities) < 2 ? null : Json::encode(ComparisonDataset::create(
                comparedActivities: $comparedActivities,
                unitSystem: $this->settingsRepository->appearance()->getUnitSystem(),
                translator: $this->translator,
            )->build()),
        ]);
    }
}
