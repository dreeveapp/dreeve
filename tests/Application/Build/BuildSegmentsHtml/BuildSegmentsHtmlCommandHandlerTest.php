<?php

namespace App\Tests\Application\Build\BuildSegmentsHtml;

use App\Application\Build\BuildSegmentsHtml\BuildSegmentsHtml;
use App\Domain\Activity\ActivityId;
use App\Domain\Activity\SportType\SportType;
use App\Domain\Segment\SegmentEffort\SegmentEffortId;
use App\Domain\Segment\SegmentEffort\SegmentEffortRepository;
use App\Domain\Segment\SegmentId;
use App\Domain\Segment\SegmentRepository;
use App\Infrastructure\Measurement\Length\Kilometer;
use App\Infrastructure\ValueObject\Geography\EncodedPolyline;
use App\Infrastructure\ValueObject\String\Name;
use App\Tests\Application\BuildAppFilesTestCase;
use App\Tests\Domain\Segment\SegmentBuilder;
use App\Tests\Domain\Segment\SegmentEffort\SegmentEffortBuilder;

class BuildSegmentsHtmlCommandHandlerTest extends BuildAppFilesTestCase
{
    public function testHandle(): void
    {
        $this->provideFullTestSet();

        $segment = SegmentBuilder::fromDefaults()
            ->withSegmentId(SegmentId::fromUnprefixed('10'))
            ->withName(Name::fromString('Segment Ten'))
            ->withDistance(Kilometer::from(0.1))
            ->withMaxGradient(5.3)
            ->withIsFavourite(true)
            ->withDeviceName('MyWhoosh')
            ->withSportType(SportType::VIRTUAL_RIDE)
            ->withPolyline(EncodedPolyline::fromString('tqafAua~y^vG{D'))
            ->build();
        $this->getContainer()->get(SegmentRepository::class)->add($segment);
        $this->getContainer()->get(SegmentRepository::class)->update($segment);

        $this->getContainer()->get(SegmentEffortRepository::class)->add(
            SegmentEffortBuilder::fromDefaults()
                ->withSegmentEffortId(SegmentEffortId::fromUnprefixed('11'))
                ->withSegmentId(SegmentId::fromUnprefixed('10'))
                ->withActivityId(ActivityId::fromUnprefixed('9542782314'))
                ->withElapsedTimeInSeconds(10.3)
                ->withAverageWatts(200)
                ->withAverageHeartRate(120)
                ->withDistance(Kilometer::from(0.1))
                ->withName('An effort')
                ->build()
        );

        $this->commandBus->dispatch(new BuildSegmentsHtml());
        $this->assertFileSystemWrites($this->getContainer()->get('build_html.storage'));
        $this->assertFileSystemWrites(
            fileSystem: $this->getContainer()->get('build_api.storage'),
            contentIsCompressed: true
        );
    }
}
