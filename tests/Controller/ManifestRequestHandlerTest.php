<?php

namespace App\Tests\Controller;

use Spatie\Snapshots\MatchesSnapshots;

class ManifestRequestHandlerTest extends ControllerWebTestCase
{
    use MatchesSnapshots;

    public function testHandle(): void
    {
        $this->client->request('GET', '/manifest.json');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'application/manifest+json');
        $this->assertMatchesJsonSnapshot((string) $this->client->getResponse()->getContent());
    }
}
