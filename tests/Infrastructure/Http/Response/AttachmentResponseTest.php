<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Http\Response;

use App\Infrastructure\Http\Response\AttachmentResponse;
use PHPUnit\Framework\TestCase;

class AttachmentResponseTest extends TestCase
{
    public function testItStripsDirectoryTraversalFromTheFileName(): void
    {
        $response = new AttachmentResponse('contents', '../../etc/passwd');

        $this->assertEquals('attachment; filename=passwd', $response->headers->get('Content-Disposition'));
    }
}
