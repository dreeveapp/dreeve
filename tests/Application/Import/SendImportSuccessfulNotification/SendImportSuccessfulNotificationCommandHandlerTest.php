<?php

namespace App\Tests\Application\Import\SendImportSuccessfulNotification;

use App\Application\AppUrl;
use App\Application\Import\ImportedActivities;
use App\Application\Import\SendImportSuccessfulNotification\SendImportSuccessfulNotification;
use App\Application\Import\SendImportSuccessfulNotification\SendImportSuccessfulNotificationCommandHandler;
use App\Domain\Activity\ActivityId;
use App\Domain\Integration\Notification\SendNotification\SendNotification;
use App\Domain\Settings\DbalSettingsRepository;
use App\Domain\Settings\SettingsGroup;
use App\Infrastructure\ValueObject\String\Url;
use App\Tests\ContainerTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use App\Tests\Infrastructure\CQRS\Command\Bus\SpyCommandBus;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class SendImportSuccessfulNotificationCommandHandlerTest extends ContainerTestCase
{
    public function testHandleWithoutImportedActivities(): void
    {
        $commandBus = new SpyCommandBus();
        $handler = new SendImportSuccessfulNotificationCommandHandler(
            commandBus: $commandBus,
            settingsRepository: $this->getContainer()->get(DbalSettingsRepository::class),
            appUrl: AppUrl::fromString('https://dreeve.test/'),
            urlGenerator: $this->getContainer()->get(UrlGeneratorInterface::class),
        );

        $handler->handle(new SendImportSuccessfulNotification(ImportedActivities::empty()));

        $this->assertEquals(
            [
                new SendNotification(
                    title: 'Import successful',
                    message: 'New import of your stats was successful',
                    tags: ['+1'],
                    actionUrl: AppUrl::fromString('https://dreeve.test/'),
                ),
            ],
            $commandBus->getDispatchedCommands(),
        );
    }

    public function testHandleWithOneImportedActivity(): void
    {
        $commandBus = new SpyCommandBus();
        $handler = new SendImportSuccessfulNotificationCommandHandler(
            commandBus: $commandBus,
            settingsRepository: $this->getContainer()->get(DbalSettingsRepository::class),
            appUrl: AppUrl::fromString('https://dreeve.test/base/'),
            urlGenerator: $this->getContainer()->get(UrlGeneratorInterface::class),
        );

        $handler->handle(new SendImportSuccessfulNotification(ImportedActivities::fromArray([
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed(1))
                ->build(),
        ])));

        $this->assertEquals(
            [
                new SendNotification(
                    title: 'Import successful',
                    message: '1 new activity was imported',
                    tags: ['+1'],
                    actionUrl: Url::fromString('https://dreeve.test/base/activities/activity-1'),
                    actionLabel: 'Open activity',
                ),
            ],
            $commandBus->getDispatchedCommands(),
        );
    }

    public function testHandleWithMultipleImportedActivities(): void
    {
        $commandBus = new SpyCommandBus();
        $handler = new SendImportSuccessfulNotificationCommandHandler(
            commandBus: $commandBus,
            settingsRepository: $this->getContainer()->get(DbalSettingsRepository::class),
            appUrl: AppUrl::fromString('https://dreeve.test'),
            urlGenerator: $this->getContainer()->get(UrlGeneratorInterface::class),
        );

        $handler->handle(new SendImportSuccessfulNotification(ImportedActivities::fromArray(array_map(
            static fn (int $number) => ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed($number))
                ->build(),
            range(1, 3),
        ))));

        $this->assertEquals(
            [
                new SendNotification(
                    title: 'Import successful',
                    message: '3 new activities were imported',
                    tags: ['+1'],
                    actionUrl: Url::fromString('https://dreeve.test/activities'),
                    actionLabel: 'Open activities',
                ),
            ],
            $commandBus->getDispatchedCommands(),
        );
    }

    public function testHandleWhenTheSuccessfulImportNotificationIsDisabled(): void
    {
        $settingsRepository = $this->getContainer()->get(DbalSettingsRepository::class);
        $settingsRepository->saveGroup(SettingsGroup::INTEGRATIONS, [
            'notifications' => ['notifyOnSuccessfulBuild' => false],
        ]);

        $commandBus = new SpyCommandBus();
        $handler = new SendImportSuccessfulNotificationCommandHandler(
            commandBus: $commandBus,
            settingsRepository: $settingsRepository,
            appUrl: AppUrl::fromString('https://dreeve.test'),
            urlGenerator: $this->getContainer()->get(UrlGeneratorInterface::class),
        );

        $handler->handle(new SendImportSuccessfulNotification(ImportedActivities::empty()));

        $this->assertEmpty($commandBus->getDispatchedCommands());
    }
}
