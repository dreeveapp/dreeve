<?php

namespace App\Tests\Application\Import;

use App\Application\AppUrl;
use App\Application\Import\ImportedActivities;
use App\Application\Import\ImportSuccessfulNotification;
use App\Domain\Activity\ActivityId;
use App\Domain\Integration\Notification\SendNotification\SendNotification;
use App\Infrastructure\ValueObject\String\Url;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class ImportSuccessfulNotificationTest extends ContainerTestCase
{
    public function testCreateWithoutImportedActivities(): void
    {
        $importSuccessfulNotification = new ImportSuccessfulNotification(
            appUrl: AppUrl::fromString('https://dreeve.test/'),
            urlGenerator: $this->getContainer()->get(UrlGeneratorInterface::class),
        );

        $this->assertEquals(
            new SendNotification(
                title: 'Import successful',
                message: 'New import of your stats was successful in 12.5s',
                tags: ['+1'],
                actionUrl: AppUrl::fromString('https://dreeve.test/'),
            ),
            $importSuccessfulNotification->create(ImportedActivities::empty(), 12.5),
        );
    }

    public function testCreateWithOneImportedActivity(): void
    {
        $importSuccessfulNotification = new ImportSuccessfulNotification(
            appUrl: AppUrl::fromString('https://dreeve.test/base/'),
            urlGenerator: $this->getContainer()->get(UrlGeneratorInterface::class),
        );

        $this->assertEquals(
            new SendNotification(
                title: 'Import successful',
                message: '1 new activity was imported',
                tags: ['+1'],
                actionUrl: Url::fromString('https://dreeve.test/base/activities/activity-1'),
                actionLabel: 'Open activity',
            ),
            $importSuccessfulNotification->create(ImportedActivities::fromArray([
                ActivityBuilder::fromDefaults()
                    ->withActivityId(ActivityId::fromUnprefixed(1))
                    ->build(),
            ]), 12.5),
        );
    }

    public function testCreateWithMultipleImportedActivities(): void
    {
        $importSuccessfulNotification = new ImportSuccessfulNotification(
            appUrl: AppUrl::fromString('https://dreeve.test'),
            urlGenerator: $this->getContainer()->get(UrlGeneratorInterface::class),
        );

        $this->assertEquals(
            new SendNotification(
                title: 'Import successful',
                message: '3 new activities were imported',
                tags: ['+1'],
                actionUrl: Url::fromString('https://dreeve.test/activities'),
                actionLabel: 'Open activities',
            ),
            $importSuccessfulNotification->create(ImportedActivities::fromArray(array_map(
                static fn (int $number) => ActivityBuilder::fromDefaults()
                    ->withActivityId(ActivityId::fromUnprefixed($number))
                    ->build(),
                range(1, 3),
            )), 12.5),
        );
    }
}
