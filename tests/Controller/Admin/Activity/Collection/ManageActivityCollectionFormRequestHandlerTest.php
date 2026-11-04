<?php

namespace App\Tests\Controller\Admin\Activity\Collection;

use App\Domain\Activity\Collection\ActivityCollectionId;
use App\Domain\Activity\Collection\ActivityCollectionRepository;
use App\Domain\Activity\Collection\ActivityCollectionType;
use App\Tests\Controller\Admin\AdminWebTestCase;
use App\Tests\Domain\Activity\Collection\ActivityCollectionBuilder;

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
    }

    public function testRendersTheEditFormPrefilledWithTheCollection(): void
    {
        $this->getContainer()->get(ActivityCollectionRepository::class)->add(ActivityCollectionBuilder::fromDefaults()
            ->withActivityCollectionId(ActivityCollectionId::fromUnprefixed('1'))
            ->withName('Transalp 2025')
            ->withDescription('Four days across the Alps')
            ->withType(ActivityCollectionType::EVENT)
            ->build());
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

        $this->client->request('GET', '/admin/collections/activityCollection-unknown/edit');
        $this->assertResponseStatusCodeSame(404);
    }
}
