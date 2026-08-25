<?php

namespace App\Tests\Controller\Admin\Settings;

use App\Tests\Controller\Admin\AdminWebTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class SettingsRequestHandlerTest extends AdminWebTestCase
{
    public function testAnonymousUsersAreRedirectedToTheLoginPage(): void
    {
        $this->client->request('GET', '/admin/settings/general');

        $this->assertResponseRedirects('/admin/login');
    }

    public function testAnonymousUsersAreRedirectedToTheLoginPageFromTheIndex(): void
    {
        $this->client->request('GET', '/admin/settings');

        $this->assertResponseRedirects('/admin/login');
    }

    public function testItRedirectsTheIndexToTheGeneralSettingsGroup(): void
    {
        $this->client->loginUser($this->adminUser());

        $this->client->request('GET', '/admin/settings');
        $this->assertResponseRedirects('/admin/settings/general');
    }

    public function testItRendersAKnownSettingsGroup(): void
    {
        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/settings/general');

        $this->assertResponseIsSuccessful();
        $this->assertCount(1, $crawler->filter('form[data-dispatch-command="update-settings"]'));
        $this->assertSame(
            ['50', '61', '71', '81', '91'],
            $crawler->filter('input[name^="data[athlete][heartRateZones][zones]"][name$="[from]"]')
                ->each(fn ($node) => $node->attr('value')),
        );
    }

    public function testItReturns404ForAnUnknownGroup(): void
    {
        $this->client->loginUser($this->adminUser());

        $this->client->request('GET', '/admin/settings/does-not-exist');

        $this->assertResponseStatusCodeSame(404);
    }

    #[DataProvider('provideDeepLinkedSettingsGroups')]
    public function testSavingReturnsTheVisitorToThePublicPageTheyCameFrom(string $group, string $redirectTo): void
    {
        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request(
            'GET',
            '/admin/settings/'.$group.'?redirectTo='.urlencode($redirectTo)
        );

        $this->assertResponseIsSuccessful();
        $this->assertSame($redirectTo, $crawler->filter('form[data-dispatch-command]')->attr('data-redirect'));
        $this->assertSame($redirectTo, $crawler->filter('nav a:has(span:contains("Return to app"))')->attr('href'));
    }

    #[DataProvider('provideDeepLinkedSettingsGroupNames')]
    public function testSavingWithoutARedirectToKeepsTheVisitorOnTheSettingsPage(string $group): void
    {
        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/settings/'.$group);

        $this->assertResponseIsSuccessful();
        // The form JS falls back to reloading when data-redirect is empty.
        $this->assertSame('', $crawler->filter('form[data-dispatch-command]')->attr('data-redirect'));
        $this->assertSame('/', $crawler->filter('nav a:has(span:contains("Return to app"))')->attr('href'));
    }

    #[DataProvider('provideUnsafeRedirectTos')]
    public function testItRefusesAnUnsafeRedirectTo(string $redirectTo): void
    {
        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request(
            'GET',
            '/admin/settings/maps?redirectTo='.urlencode($redirectTo)
        );

        $this->assertResponseIsSuccessful();
        $this->assertSame('', $crawler->filter('form[data-dispatch-command]')->attr('data-redirect'));
    }

    public static function provideDeepLinkedSettingsGroups(): \Generator
    {
        yield 'maps, linked from the heatmap' => ['maps', '/heatmap'];
        yield 'metrics, linked from eddington' => ['metrics', '/eddington'];
        yield 'integrations, linked from the chat' => ['integrations', '/chat'];
    }

    public static function provideDeepLinkedSettingsGroupNames(): \Generator
    {
        foreach (self::provideDeepLinkedSettingsGroups() as $name => [$group]) {
            yield $name => [$group];
        }
    }

    public static function provideUnsafeRedirectTos(): \Generator
    {
        yield 'protocol relative' => ['//evil.com'];
        yield 'javascript uri' => ['javascript:alert(1)'];
        yield 'absolute url' => ['https://evil.com'];
    }
}
