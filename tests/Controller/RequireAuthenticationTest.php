<?php

namespace App\Tests\Controller;

use App\Domain\Settings\SettingsGroup;
use App\Domain\Settings\UpdateSettings\UpdateSettings;
use App\Infrastructure\CQRS\Command\Bus\CommandBus;
use App\Infrastructure\KeyValue\KeyValue;
use App\Infrastructure\KeyValue\KeyValueStore;
use App\Infrastructure\KeyValue\Value;
use App\Infrastructure\Serialization\Json;
use App\Tests\Controller\Admin\AdminWebTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class RequireAuthenticationTest extends AdminWebTestCase
{
    #[DataProvider('provideProtectedPaths')]
    public function testItSendsAnonymousVisitorsToTheLoginPage(string $path): void
    {
        $this->requireAuthentication();

        $this->client->request('GET', $path);

        $this->assertResponseRedirects('http://localhost/admin/login');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideProtectedPaths(): iterable
    {
        yield 'dashboard' => ['/'];
        yield 'fragment api' => ['/api/internal/fragment/page/dashboard'];
        yield 'badge' => ['/badge/strava.svg'];
        yield 'images' => ['/files/gear/bike.png'];
    }

    #[DataProvider('providePublicPaths')]
    public function testItKeepsTheSetupAndWebhookPathsPublic(string $path): void
    {
        $this->requireAuthentication();

        $this->client->request('GET', $path);

        // /finish-setup answers with a redirect of its own once activities exist, so all
        // that matters here is that the firewall did not step in.
        $this->assertNotEquals(
            'http://localhost/admin/login',
            $this->client->getResponse()->headers->get('Location')
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providePublicPaths(): iterable
    {
        yield 'manifest' => ['/manifest.json'];
        yield 'finish setup' => ['/finish-setup'];
        yield 'login' => ['/admin/login'];
        yield 'strava webhook' => ['/strava/webhook'];
    }

    public function testItStaysPublicWhileTheSettingIsOff(): void
    {
        $this->client->request('GET', '/');

        $this->assertResponseIsSuccessful();
    }

    public function testItServesEverythingToAnAuthenticatedVisitor(): void
    {
        $this->requireAuthentication();
        $this->client->loginUser($this->adminUser());

        $this->client->request('GET', '/');

        $this->assertResponseIsSuccessful();
    }

    public function testItTakesEffectAsSoonAsTheSettingIsSaved(): void
    {
        $this->getContainer()->get(CommandBus::class)->dispatch(UpdateSettings::fromPayload([
            'group' => SettingsGroup::SECURITY->value,
            'data' => ['requiresAuthentication' => true],
        ]));

        $this->client->request('GET', '/');

        $this->assertResponseRedirects('http://localhost/admin/login');
    }

    private function requireAuthentication(): void
    {
        /** @var KeyValueStore $keyValueStore */
        $keyValueStore = $this->getContainer()->get(KeyValueStore::class);
        $keyValueStore->save(KeyValue::fromState(
            SettingsGroup::SECURITY->keyValueKey(),
            Value::fromString(Json::encode(['requiresAuthentication' => true])),
        ));
    }
}
