<?php

namespace App\Tests\Controller\Admin\Activity\Media;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Media\MediaItemId;
use App\Domain\Media\MediaItemPath;
use App\Domain\Media\MediaItemRepository;
use App\Infrastructure\ValueObject\Geography\Coordinate;
use App\Infrastructure\ValueObject\Geography\Latitude;
use App\Infrastructure\ValueObject\Geography\Longitude;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\Controller\Admin\AdminWebTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use App\Tests\Domain\Media\MediaItemBuilder;

class ManageActivityMediaItemFormRequestHandlerTest extends AdminWebTestCase
{
    public function testRendersTheEditFormPrefilledWithTheMediaItem(): void
    {
        $mediaItemRepository = static::getContainer()->get(MediaItemRepository::class);
        $mediaItemRepository->add(MediaItemBuilder::fromDefaults()
            ->withMediaItemId(MediaItemId::fromUnprefixed('photo'))
            ->withPath(MediaItemPath::fromString('activities/photo.jpg'))
            ->withCoordinate(Coordinate::createFromLatAndLng(
                latitude: Latitude::fromString('51.0543'),
                longitude: Longitude::fromString('3.7174'),
            ))
            ->withTakenOn(SerializableDateTime::fromString('2025-06-14 09:30:45'))
            ->build());
        $mediaItemRepository->add(MediaItemBuilder::fromDefaults()
            ->withMediaItemId(MediaItemId::fromUnprefixed('other'))
            ->withPath(MediaItemPath::fromString('activities/other.jpg'))
            ->build());
        static::getContainer()->get(ActivityRepository::class)->add(ActivityWithRawData::fromState(
            ActivityBuilder::fromDefaults()
                ->withActivityId(ActivityId::fromUnprefixed('ride'))
                ->withMediaItemIds(MediaItemId::fromUnprefixed('photo'))
                ->build(),
            [],
        ));

        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/activities/'.ActivityId::fromUnprefixed('ride').'/media/'.MediaItemId::fromUnprefixed('photo').'/edit');

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('Edit media', $crawler->filter('h3')->text());
        $this->assertCount(1, $crawler->filter('link[href*="libraries/leaflet/leaflet.min.css"]'));
        $this->assertSame('/admin/activities/'.ActivityId::fromUnprefixed('ride').'/edit', $crawler->filter('a[aria-label="Close"]')->attr('href'));

        $form = $crawler->filter('form[data-dispatch-command="update-media-item"]');
        $this->assertCount(1, $form);

        $this->assertSame('/admin/activities/'.ActivityId::fromUnprefixed('ride').'/edit', $form->attr('data-redirect'));
        $this->assertSame((string) MediaItemId::fromUnprefixed('photo'), $form->filter('input[name="mediaItemId"]')->attr('value'));
        $this->assertSame('/files/activities/photo.jpg', $form->filter('img')->attr('src'));
        $this->assertSame('2025-06-14T09:30', $form->filter('input[name="takenOn"]')->attr('value'));
        $this->assertSame('51.0543', $form->filter('input[name="latitude"]')->attr('value'));
        $this->assertSame('3.7174', $form->filter('input[name="longitude"]')->attr('value'));

        $this->client->request('GET', '/admin/activities/'.ActivityId::fromUnprefixed('unknown').'/media/'.MediaItemId::fromUnprefixed('photo').'/edit');
        $this->assertResponseStatusCodeSame(404);

        $this->client->request('GET', '/admin/activities/'.ActivityId::fromUnprefixed('ride').'/media/'.MediaItemId::fromUnprefixed('unknown').'/edit');
        $this->assertResponseStatusCodeSame(404);

        $this->client->request('GET', '/admin/activities/'.ActivityId::fromUnprefixed('ride').'/media/'.MediaItemId::fromUnprefixed('other').'/edit');
        $this->assertResponseStatusCodeSame(404);
    }
}
