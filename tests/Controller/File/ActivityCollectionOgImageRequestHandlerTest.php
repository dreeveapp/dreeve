<?php

declare(strict_types=1);

namespace App\Tests\Controller\File;

use App\Application\PageMetadata\OpenGraphImageCanvas;
use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityIds;
use App\Domain\Activity\Collection\ActivityCollectionRepository;
use App\Domain\Activity\Collection\ActivityCollectionType;
use App\Tests\Controller\ControllerWebTestCase;
use App\Tests\Domain\Activity\Collection\ActivityCollectionBuilder;
use App\Tests\ProvideTestData;

class ActivityCollectionOgImageRequestHandlerTest extends ControllerWebTestCase
{
    use ProvideTestData;

    public function testItServesTheCardAsAPublicPng(): void
    {
        $this->provideFullTestSet();
        $this->getContainer()->get(ActivityCollectionRepository::class)->add(ActivityCollectionBuilder::fromDefaults()
            ->withName('Week in Bruges')
            ->withType(ActivityCollectionType::TRIP)
            ->withActivityIds(ActivityIds::fromArray([
                ActivityId::fromUnprefixed('9830227167'),
                ActivityId::fromUnprefixed('9830227112'),
                ActivityId::fromUnprefixed('9830232182'),
            ]))
            ->build());

        $this->client->request('GET', '/collections/activityCollection-1/og-image.png');

        $this->assertResponseIsSuccessful();
        $this->assertEquals('activity_collection_og_image', $this->client->getRequest()->attributes->get('_route'));
        $this->assertResponseHeaderSame('Content-Type', 'image/png');
        $this->assertResponseHeaderSame('Cache-Control', 'max-age=3600, public');

        $size = getimagesizefromstring($this->client->getInternalResponse()->getContent());
        $this->assertIsArray($size);
        $this->assertEquals([OpenGraphImageCanvas::WIDTH, OpenGraphImageCanvas::HEIGHT], [$size[0], $size[1]]);
    }

    public function testItReturnsNotFound(): void
    {
        $this->client->request('GET', '/collections/activityCollection-1/og-image.png');
        $this->assertResponseStatusCodeSame(404);

        $this->getContainer()->get(ActivityCollectionRepository::class)->add(ActivityCollectionBuilder::fromDefaults()
            ->withActivityIds(ActivityIds::fromArray([ActivityId::fromUnprefixed('deleted')]))
            ->build());

        $this->client->request('GET', '/collections/activityCollection-1/og-image.png');
        $this->assertResponseStatusCodeSame(404);
    }
}
