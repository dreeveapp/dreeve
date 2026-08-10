<?php

namespace App\Tests\Controller\Admin;

use App\Infrastructure\Serialization\Json;
use League\Flysystem\FilesystemOperator;

class ImportStatusRequestHandlerTest extends AdminWebTestCase
{
    private FilesystemOperator $watchStorage;

    public function testAnonymousUsersAreRedirectedToTheLoginPage(): void
    {
        $this->client->request('GET', '/admin/importStatus');

        $this->assertResponseRedirects('/admin/login');
    }

    public function testItIsPendingWhenTheWatchDirectoryHoldsAProcessableFile(): void
    {
        $this->client->loginUser($this->adminUser());
        $this->watchStorage->write('watch/ride.fit', 'raw-fit-bytes');

        $this->client->request('GET', '/admin/importStatus');

        $this->assertResponseIsSuccessful();
        $this->assertSame(['pending' => true], Json::decode($this->client->getResponse()->getContent()));
    }

    public function testItIsNotPendingWhenTheWatchDirectoryIsEmpty(): void
    {
        $this->client->loginUser($this->adminUser());

        $this->client->request('GET', '/admin/importStatus');

        $this->assertResponseIsSuccessful();
        $this->assertSame(['pending' => false], Json::decode($this->client->getResponse()->getContent()));
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->watchStorage = $this->getContainer()->get('default.storage');
    }

    #[\Override]
    protected function tearDown(): void
    {
        $this->watchStorage->deleteDirectory('watch');

        parent::tearDown();
    }
}
