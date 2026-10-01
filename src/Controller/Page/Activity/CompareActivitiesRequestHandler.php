<?php

declare(strict_types=1);

namespace App\Controller\Page\Activity;

use App\Application\Navigation\NavigationSection;
use App\Application\PageRenderer;
use App\Domain\Activity\ActivityCacheTag;
use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityIds;
use App\Domain\Activity\Comparison\ComparedActivities;
use App\Domain\Activity\Comparison\ComparedActivity;
use App\Domain\Activity\Comparison\ComparisonDataset;
use App\Domain\Activity\EnrichedActivityRepository;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\Tag\CacheTags;
use App\Infrastructure\Cache\Tag\RootCacheTag;
use App\Infrastructure\Http\HtmlResponse;
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
        private PageRenderer $pageRenderer,
    ) {
    }

    #[Route(path: '/activities/compare', name: 'activities_compare', methods: ['GET'])]
    public function handle(Request $request): HtmlResponse
    {
        $activityIds = [];
        foreach (explode(',', (string) $request->query->get('activities', '')) as $activityId) {
            try {
                $activityId = ActivityId::fromString(trim($activityId));
            } catch (\InvalidArgumentException) {
                continue;
            }
            $activityIds[$activityId->toUnprefixedString()] = $activityId;
        }
        ksort($activityIds);
        $activityIds = array_values($activityIds);

        return $this->pageRenderer->render(
            cacheability: Cacheability::for(
                cacheKey: implode('.', [
                    'activities.compare',
                    ...array_map(fn (ActivityId $activityId): string => $activityId->toUnprefixedString(), $activityIds),
                ]),
                cacheTags: CacheTags::of(
                    RootCacheTag::ACTIVITIES,
                    ...array_map(ActivityCacheTag::for(...), $activityIds),
                ),
            ),
            render: fn (): string => $this->renderFor(ActivityIds::fromArray($activityIds)),
            navigationSection: NavigationSection::ACTIVITIES,
        );
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
