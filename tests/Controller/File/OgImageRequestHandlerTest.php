<?php

namespace App\Tests\Controller\File;

use App\Application\PageMetadata\OpenGraphImageCanvas;
use App\Tests\Controller\ControllerWebTestCase;

class OgImageRequestHandlerTest extends ControllerWebTestCase
{
    public function testItServesTheCardAsAPublicPng(): void
    {
        $this->client->request('GET', '/og-image.png');

        $this->assertResponseIsSuccessful();
        $this->assertEquals('og_image', $this->client->getRequest()->attributes->get('_route'));
        $this->assertResponseHeaderSame('Content-Type', 'image/png');
        $this->assertResponseHeaderSame('Cache-Control', 'max-age=3600, public');

        $size = getimagesizefromstring($this->client->getInternalResponse()->getContent());
        $this->assertIsArray($size);
        $this->assertEquals([OpenGraphImageCanvas::WIDTH, OpenGraphImageCanvas::HEIGHT], [$size[0], $size[1]]);
    }
}
