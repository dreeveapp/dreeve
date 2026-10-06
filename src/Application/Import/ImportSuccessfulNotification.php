<?php

declare(strict_types=1);

namespace App\Application\Import;

use App\Application\AppUrl;
use App\Domain\Activity\Activity;
use App\Domain\Integration\Notification\SendNotification\SendNotification;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class ImportSuccessfulNotification
{
    public function __construct(
        private AppUrl $appUrl,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function create(ImportedActivities $importedActivities, float $runTimeInSeconds): SendNotification
    {
        $numberOfImportedActivities = count($importedActivities);

        if (0 === $numberOfImportedActivities) {
            return new SendNotification(
                title: 'Import successful',
                message: sprintf('New import of your stats was successful in %ss', $runTimeInSeconds),
                tags: ['+1'],
                actionUrl: $this->appUrl,
            );
        }

        if (1 === $numberOfImportedActivities) {
            /** @var Activity $activity */
            $activity = $importedActivities->getFirst();

            return new SendNotification(
                title: 'Import successful',
                message: '1 new activity was imported',
                tags: ['+1'],
                actionUrl: $this->appUrl->withPath($this->urlGenerator->generate('activity', ['activityId' => (string) $activity->getId()])),
                actionLabel: 'Open activity',
            );
        }

        return new SendNotification(
            title: 'Import successful',
            message: sprintf('%d new activities were imported', $numberOfImportedActivities),
            tags: ['+1'],
            actionUrl: $this->appUrl->withPath($this->urlGenerator->generate('activities')),
            actionLabel: 'Open activities',
        );
    }
}
