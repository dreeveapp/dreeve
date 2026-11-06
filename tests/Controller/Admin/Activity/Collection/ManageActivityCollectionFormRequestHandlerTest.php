<?php

namespace App\Tests\Controller\Admin\Activity\Collection;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityIds;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\ActivityWithRawData;
use App\Domain\Activity\Collection\ActivityCollectionId;
use App\Domain\Activity\Collection\ActivityCollectionRepository;
use App\Domain\Activity\Collection\ActivityCollectionType;
use App\Domain\Activity\SportType\SportType;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\Controller\Admin\AdminWebTestCase;
use App\Tests\Domain\Activity\ActivityBuilder;
use App\Tests\Domain\Activity\Collection\ActivityCollectionBuilder;
use Symfony\Component\DomCrawler\Crawler;

class ManageActivityCollectionFormRequestHandlerTest extends AdminWebTestCase
{
    public function testRendersTheAddForm(): void
    {
        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/collections/add');

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('Add collection', $crawler->filter('h3')->text());

        $form = $crawler->filter('form[data-dispatch-command="add-activity-collection"]');
        $this->assertCount(1, $form);
        $activityCollectionId = (string) $form->filter('input[type="hidden"][name="activityCollectionId"]')->attr('value');
        $this->assertStringStartsWith('activityCollection-', $activityCollectionId);
        $this->assertSame('/admin/collections/'.$activityCollectionId.'/edit', $form->attr('data-redirect'));
        $this->assertSame('', $form->filter('input[name="name"]')->attr('value'));
        $this->assertSame(['other', 'trip', 'event'], $form->filter('select[name="type"] option')->extract(['value']));
        $this->assertCount(0, $form->filter('select[name="type"] option[selected]'));
        $this->assertSame('', $form->filter('textarea[name="description"]')->text());
        $this->assertCount(0, $crawler->filter('a.btn--danger'));
        $this->assertCount(0, $form->filter('input[name="activityIds[]"]'));
        $this->assertSame('[]', $form->filter('[data-image-dropzone]')->attr('data-existing-images'));
        $this->assertCount(1, $form->filter('input[data-images-field][name="coverImagePath"]'));
        $this->assertStringContainsString("Without a cover image, the first photo of the collection's activities is used.", $form->text());
        $this->assertSame('Save and add activities', trim($form->filter('button[type="submit"] .label')->text()));
        $this->assertCount(1, $crawler->filter('.max-w-2xl'));
    }

    public function testRendersTheEditFormPrefilledWithTheCollection(): void
    {
        $this->getContainer()->get(ActivityCollectionRepository::class)->add(ActivityCollectionBuilder::fromDefaults()
            ->withActivityCollectionId(ActivityCollectionId::fromUnprefixed('1'))
            ->withName('Transalp 2025')
            ->withDescription('Four days across the Alps')
            ->withCoverImagePath('/files/activity-collections/cover.jpg')
            ->withType(ActivityCollectionType::EVENT)
            ->withActivityIds(ActivityIds::fromArray([
                ActivityId::fromUnprefixed('3'),
                ActivityId::fromUnprefixed('2'),
                ActivityId::fromUnprefixed('deleted'),
            ]))
            ->build());
        $activityRepository = $this->getContainer()->get(ActivityRepository::class);
        $activityRepository->add(ActivityWithRawData::fromState(ActivityBuilder::fromDefaults()
            ->withActivityId(ActivityId::fromUnprefixed('2'))
            ->withName('Stage 1')
            ->withSportType(SportType::RIDE)
            ->withStartDateTime(SerializableDateTime::fromString('2025-06-22 08:00:00'))
            ->build(), []));
        $activityRepository->add(ActivityWithRawData::fromState(ActivityBuilder::fromDefaults()
            ->withActivityId(ActivityId::fromUnprefixed('3'))
            ->withName('Stage 2')
            ->withSportType(SportType::GRAVEL_RIDE)
            ->withStartDateTime(SerializableDateTime::fromString('2025-06-23 08:00:00'))
            ->build(), []));
        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/collections/activityCollection-1/edit');

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('Edit collection', $crawler->filter('h3')->text());

        $form = $crawler->filter('form[data-dispatch-command="update-activity-collection"]');
        $this->assertCount(1, $form);
        $this->assertSame('/admin/collections', $form->attr('data-redirect'));
        $this->assertSame('activityCollection-1', $form->filter('input[type="hidden"][name="activityCollectionId"]')->attr('value'));
        $this->assertSame('Transalp 2025', $form->filter('input[name="name"]')->attr('value'));
        $this->assertSame('event', $form->filter('select[name="type"] option[selected]')->attr('value'));
        $this->assertSame('Four days across the Alps', $form->filter('textarea[name="description"]')->text());
        $this->assertSame(
            [['url' => '/files/activity-collections/cover.jpg', 'name' => 'cover.jpg', 'path' => '/files/activity-collections/cover.jpg']],
            json_decode((string) $form->filter('[data-image-dropzone]')->attr('data-existing-images'), true),
        );
        $this->assertSame('/admin/collections/activityCollection-1/delete', $form->filter('a.btn--danger')->attr('href'));

        $this->assertCount(1, $crawler->filter('.modal-like-form .btn--primary'));
        $this->assertSame(
            [['', 'Stage 1', 'Ride', '22-06-25'], ['', 'Stage 2', 'Gravel Ride', '23-06-25']],
            $form->filter('table.data-table tbody tr')->each(
                static fn (Crawler $row): array => $row->filter('td')->each(static fn (Crawler $cell): string => trim($cell->text())),
            ),
        );
        $this->assertSame(
            ['activity-2', 'activity-3'],
            $form->filter('table.data-table input[type="checkbox"][name="activityIds[]"][checked]')->extract(['value']),
        );
        $this->assertSame('/admin/activities/search', $form->filter('input[type="text"][name="activityIds[]"]')->attr('data-autocomplete-url'));
        $this->assertSame(
            '/admin/collections/activityCollection-1/edit?redirectTo=%2Fadmin%2Fcollections',
            $form->filter('button[type="submit"].btn--secondary')->attr('data-redirect'),
        );
        $this->assertSame('Save', trim($form->filter('button[type="submit"].btn--primary .label')->text()));

        $crawler = $this->client->request('GET', '/admin/collections/activityCollection-1/edit?redirectTo='.urlencode('/collections/activityCollection-1'));
        $this->assertSame('/collections/activityCollection-1', $crawler->filter('form[data-dispatch-command="update-activity-collection"]')->attr('data-redirect'));
        $this->assertSame('/collections/activityCollection-1', $crawler->filter('a.btn--secondary')->attr('href'));
        $this->assertSame(
            '/admin/collections/activityCollection-1/edit?redirectTo=%2Fcollections%2FactivityCollection-1',
            $crawler->filter('button[type="submit"].btn--secondary')->attr('data-redirect'),
        );

        $this->client->request('GET', '/admin/collections/activityCollection-unknown/edit');
        $this->assertResponseStatusCodeSame(404);
    }

    public function testRendersTheDeleteConfirmation(): void
    {
        $this->getContainer()->get(ActivityCollectionRepository::class)->add(ActivityCollectionBuilder::fromDefaults()
            ->withActivityCollectionId(ActivityCollectionId::fromUnprefixed('1'))
            ->withName('Mallorca')
            ->build());
        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/collections/activityCollection-1/delete');

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('Delete collection', $crawler->filter('h3')->text());

        $form = $crawler->filter('form[data-dispatch-command="delete-activity-collection"]');
        $this->assertSame('/admin/collections', $form->attr('data-redirect'));
        $this->assertSame('activityCollection-1', $form->filter('input[type="hidden"][name="activityCollectionId"]')->attr('value'));
        $this->assertCount(1, $form->filter('button[type="submit"].btn--danger'));
        $this->assertStringContainsString('Are you sure you want to delete Mallorca?', $form->text());

        $this->client->request('GET', '/admin/collections/activityCollection-unknown/delete');
        $this->assertResponseStatusCodeSame(404);
    }
}
