<?php

declare(strict_types=1);

namespace App\Tests;

use App\Domain\Activity\ActivityIdRepository;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Tests\Domain\Activity\ActivityBuilder;
use League\Flysystem\FilesystemOperator;
use Symfony\Component\DependencyInjection\Container;

trait ProvideBuiltApp
{
    abstract protected static function getContainer(): Container;

    protected function markAppAsBuilt(): void
    {
        /** @var FilesystemOperator $buildHtmlStorage */
        $buildHtmlStorage = $this->getContainer()->get('build_html.storage');
        $buildHtmlStorage->write('index.html', 'I am the index');

        /** @var ActivityIdRepository $activityIdRepository */
        $activityIdRepository = $this->getContainer()->get(ActivityIdRepository::class);
        if ($activityIdRepository->count() > 0) {
            return;
        }

        /** @var ActivityRepository $activityRepository */
        $activityRepository = $this->getContainer()->get(ActivityRepository::class);
        $activityRepository->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()->build(),
            [],
        ));
    }
}
