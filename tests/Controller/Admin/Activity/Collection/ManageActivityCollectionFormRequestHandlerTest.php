<?php

namespace App\Tests\Controller\Admin\Activity\Collection;

use App\Tests\Controller\Admin\AdminWebTestCase;

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
        $this->assertSame('/admin/collections', $form->attr('data-redirect'));
        $this->assertStringStartsWith('activityCollection-', (string) $form->filter('input[type="hidden"][name="activityCollectionId"]')->attr('value'));
        $this->assertSame('', $form->filter('input[name="name"]')->attr('value'));
        $this->assertSame(['other', 'trip', 'event'], $form->filter('select[name="type"] option')->extract(['value']));
        $this->assertSame('', $form->filter('textarea[name="description"]')->text());
    }
}
