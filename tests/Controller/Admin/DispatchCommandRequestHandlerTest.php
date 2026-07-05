<?php

namespace App\Tests\Controller\Admin;

use App\Domain\Dashboard\AddWidget\AddWidget;
use App\Domain\Import\UploadActivityFile\UploadActivityFile;
use App\Infrastructure\CQRS\Command\Bus\CommandBus;
use App\Infrastructure\KeyValue\Key;
use App\Infrastructure\KeyValue\KeyValue;
use App\Infrastructure\KeyValue\KeyValueStore;
use App\Infrastructure\KeyValue\Value;
use App\Infrastructure\Serialization\Json;
use App\Tests\Infrastructure\CQRS\Command\Bus\SpyCommandBus;
use Symfony\Component\HttpFoundation\Response;

class DispatchCommandRequestHandlerTest extends AdminWebTestCase
{
    public function testAnonymousUsersAreRedirectedToTheLoginPage(): void
    {
        $this->client->request('POST', '/admin/dispatchCommand');

        $this->assertResponseRedirects('/admin/login');
    }

    public function testHandle(): void
    {
        $this->client->loginUser($this->adminUser());
        $this->client->disableReboot();

        $spyCommandBus = new SpyCommandBus();
        static::getContainer()->set(CommandBus::class, $spyCommandBus);

        $this->client->request(
            method: 'POST',
            uri: '/admin/dispatchCommand',
            server: ['HTTP_X_CSRF_TOKEN' => $this->validCsrfToken()],
            content: Json::encode([
                'commandName' => 'upload-activity-file',
                'payload' => [
                    'filename' => 'ride.fit',
                    'content' => base64_encode('raw-fit-bytes'),
                ],
            ]),
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $dispatchedCommands = $spyCommandBus->getDispatchedCommands();
        $this->assertCount(1, $dispatchedCommands);
        $this->assertInstanceOf(UploadActivityFile::class, $dispatchedCommands[0]);

        $this->assertSame(
            [],
            $this->client->getRequest()->getSession()->getFlashBag()->peek('success'),
        );
    }

    public function testHandleAddsASuccessFlashMessageForCommandsThatDoNotSuppressIt(): void
    {
        $this->client->loginUser($this->adminUser());
        $this->client->disableReboot();

        $spyCommandBus = new SpyCommandBus();
        static::getContainer()->set(CommandBus::class, $spyCommandBus);

        $this->client->request(
            method: 'POST',
            uri: '/admin/dispatchCommand',
            server: ['HTTP_X_CSRF_TOKEN' => $this->validCsrfToken()],
            content: Json::encode([
                'commandName' => 'add-widget',
                'payload' => [
                    'widget' => 'eddington',
                ],
            ]),
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $dispatchedCommands = $spyCommandBus->getDispatchedCommands();
        $this->assertCount(1, $dispatchedCommands);
        $this->assertInstanceOf(AddWidget::class, $dispatchedCommands[0]);

        $this->assertSame(
            ['Your changes have been saved.'],
            $this->client->getRequest()->getSession()->getFlashBag()->peek('success'),
        );
    }

    public function testHandleWithInvalidCsrfToken(): void
    {
        $this->client->loginUser($this->adminUser());

        $this->client->request(
            method: 'POST',
            uri: '/admin/dispatchCommand',
            server: ['HTTP_X_CSRF_TOKEN' => 'a-tampered-token'],
            content: Json::encode([
                'commandName' => 'upload-activity-file',
                'payload' => [
                    'filename' => 'ride.fit',
                    'content' => base64_encode('raw-fit-bytes'),
                ],
            ]),
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testHandleWithUnknownCommand(): void
    {
        $this->client->loginUser($this->adminUser());
        $this->client->disableReboot();

        $this->client->request(
            method: 'POST',
            uri: '/admin/dispatchCommand',
            server: ['HTTP_X_CSRF_TOKEN' => $this->validCsrfToken()],
            content: Json::encode([
                'commandName' => 'not-a-known-command',
                'payload' => [],
            ]),
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testHandleUploadActivityFileWithUnsupportedExtension(): void
    {
        $this->client->loginUser($this->adminUser());
        $this->client->disableReboot();

        $spyCommandBus = new SpyCommandBus();
        static::getContainer()->set(CommandBus::class, $spyCommandBus);

        $this->client->request(
            method: 'POST',
            uri: '/admin/dispatchCommand',
            server: ['HTTP_X_CSRF_TOKEN' => $this->validCsrfToken()],
            content: Json::encode([
                'commandName' => 'upload-activity-file',
                'payload' => [
                    'filename' => 'notes.txt',
                    'content' => base64_encode('some text'),
                ],
            ]),
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->assertCount(0, $spyCommandBus->getDispatchedCommands());
    }

    public function testHandleWithInvalidContent(): void
    {
        $this->client->loginUser($this->adminUser());
        $this->client->disableReboot();

        $this->client->request(
            method: 'POST',
            uri: '/admin/dispatchCommand',
            server: ['HTTP_X_CSRF_TOKEN' => $this->validCsrfToken()],
            content: Json::encode(['not' => 'a command']),
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testHandleReturnsTheValidationMessageWhenAWidgetConfigurationIsInvalid(): void
    {
        $this->client->loginUser($this->adminUser());
        $this->client->disableReboot();

        /** @var KeyValueStore $keyValueStore */
        $keyValueStore = static::getContainer()->get(KeyValueStore::class);
        $keyValueStore->save(KeyValue::fromState(
            key: Key::DASHBOARD,
            value: Value::fromString(Json::encode([
                ['id' => 'dashboardWidget-mostRecentActivities', 'widget' => 'mostRecentActivities', 'width' => 66, 'config' => ['numberOfActivitiesToDisplay' => 5]],
            ])),
        ));

        $this->client->request(
            method: 'POST',
            uri: '/admin/dispatchCommand',
            server: ['HTTP_X_CSRF_TOKEN' => $this->validCsrfToken()],
            content: Json::encode([
                'commandName' => 'configure-widget',
                'payload' => [
                    'dashboardWidgetId' => 'dashboardWidget-mostRecentActivities',
                    'config' => ['numberOfActivitiesToDisplay' => '0'],
                ],
            ]),
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $this->assertSame(
            ['error' => 'Configuration item "numberOfActivitiesToDisplay" must be set to a value of 1 or greater.'],
            Json::decode((string) $this->client->getResponse()->getContent()),
        );
    }

    private function validCsrfToken(): string
    {
        $crawler = $this->client->request('GET', '/admin/upload');

        return $crawler->filter('meta[name="csrf-token"]')->attr('content');
    }
}
