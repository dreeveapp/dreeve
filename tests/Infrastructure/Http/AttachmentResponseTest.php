<?php

namespace App\Tests\Infrastructure\Http;

use App\Infrastructure\Http\AttachmentResponse;
use PHPUnit\Framework\TestCase;

class AttachmentResponseTest extends TestCase
{
    public function testItDefaultsToABinaryContentType(): void
    {
        $response = new AttachmentResponse('contents', 'activity.fit');

        $this->assertEquals('application/octet-stream', $response->headers->get('Content-Type'));
        $this->assertEquals('attachment; filename=activity.fit', $response->headers->get('Content-Disposition'));
    }

    public function testItUsesTheGivenContentType(): void
    {
        $response = new AttachmentResponse('contents', 'route.gpx', 'application/gpx+xml; charset=UTF-8');

        $this->assertEquals('application/gpx+xml; charset=UTF-8', $response->headers->get('Content-Type'));
    }

    public function testItStripsDirectoryTraversalFromTheFileName(): void
    {
        $response = new AttachmentResponse('contents', '../../etc/passwd');

        $this->assertEquals('attachment; filename=passwd', $response->headers->get('Content-Disposition'));
    }

    public function testItAddsAnAsciiFallbackForNonAsciiFileNames(): void
    {
        $response = new AttachmentResponse('contents', '2023-09-04-avondrit-brugge-é.gpx');

        $this->assertEquals(
            'attachment; filename=2023-09-04-avondrit-brugge-__.gpx; filename*=utf-8\'\'2023-09-04-avondrit-brugge-%C3%A9.gpx',
            $response->headers->get('Content-Disposition')
        );
    }
}
