<?php

namespace App\Tests\Controller\Admin\Settings;

use App\Tests\Controller\Admin\AdminWebTestCase;
use PHPUnit\Framework\Attributes\TestWith;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class EditWidgetRequestHandlerTest extends AdminWebTestCase
{
    public function testRendersTheDeleteConfirmation(): void
    {
        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/settings/dashboard/widget/dashboardWidget-eddington/delete');

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('Remove widget', $crawler->filter('h3')->text());

        $form = $crawler->filter('form[data-dispatch-command="delete-widget"]');
        $this->assertCount(1, $form);
        $this->assertSame('dashboardWidget-eddington', $form->filter('input[name="dashboardWidgetId"]')->attr('value'));
        $this->assertCount(1, $form->filter('button.btn--danger'));
        $this->assertStringContainsString('Eddington', $form->text());
    }

    #[TestWith(['dashboardWidget-does-not-exist/delete'])]
    #[TestWith(['dashboardWidget-does-not-exist/configure'])]
    #[TestWith(['dashboardWidget-introText/configure'])]
    public function testReturns404ForAWidgetThatCannotBeManaged(string $path): void
    {
        $this->client->loginUser($this->adminUser());
        $this->client->catchExceptions(false);

        $this->expectExceptionObject(new NotFoundHttpException('Widget not found'));
        $this->client->request('GET', '/admin/settings/dashboard/widget/'.$path);
    }

    public function testRendersTheConfigurationForm(): void
    {
        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/settings/dashboard/widget/dashboardWidget-streaks/configure');

        $this->assertResponseIsSuccessful();

        $form = $crawler->filter('form[data-dispatch-command="configure-widget"]');
        $this->assertCount(1, $form);
        $this->assertSame('dashboardWidget-streaks', $form->filter('input[name="dashboardWidgetId"]')->attr('value'));
        $this->assertCount(1, $form->filter('input[name="config[title]"]'));
        $this->assertCount(1, $form->filter('input[name="config[subtitle]"]'));
        $this->assertGreaterThan(0, $form->filter('input[type="checkbox"][name="config[sportTypesToInclude][]"]')->count());
    }

    public function testRendersTheConfigurationFormForAWidgetWithoutItsOwnOptions(): void
    {
        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/settings/dashboard/widget/dashboardWidget-eddington/configure');

        $this->assertResponseIsSuccessful();

        $form = $crawler->filter('form[data-dispatch-command="configure-widget"]');
        $this->assertCount(1, $form);
        $this->assertCount(1, $form->filter('input[name="config[title]"]'));
        $this->assertSame('Eddington', $form->filter('input[name="config[title]"]')->attr('placeholder'));
    }
}
