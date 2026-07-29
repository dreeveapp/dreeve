<?php

declare(strict_types=1);

namespace App\Tests\Application\Build;

use App\Application\Build\ActivityPageBuildStatus;
use App\Domain\Activity\ActivityId;
use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

class ActivityPageBuildStatusTest extends TestCase
{
    private Filesystem $buildHtmlStorage;
    private ActivityPageBuildStatus $activityPageBuildStatus;

    #[TestWith(data: ['activity/activity-1.html', '1', true])]
    #[TestWith(data: [null, '1', false])]
    #[TestWith(data: ['activity/activity-1.html', '2', false])]
    #[TestWith(data: ['activity/1.html', '1', false])]
    public function testHasBeenBuilt(?string $builtPage, string $activityId, bool $expected): void
    {
        if (null !== $builtPage) {
            $this->buildHtmlStorage->write($builtPage, 'I am the activity page');
        }

        $this->assertSame(
            $expected,
            $this->activityPageBuildStatus->hasBeenBuilt(ActivityId::fromUnprefixed($activityId))
        );
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->buildHtmlStorage = new Filesystem(new InMemoryFilesystemAdapter());
        $this->activityPageBuildStatus = new ActivityPageBuildStatus($this->buildHtmlStorage);
    }
}
