<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;

class AttachmentResponse extends Response
{
    public function __construct(string $contents, string $filename)
    {
        $filename = basename(str_replace('\\', '/', $filename));
        $filenameFallback = (string) preg_replace('/[^A-Za-z0-9._-]/', '_', $filename);

        parent::__construct($contents, Response::HTTP_OK, [
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => HeaderUtils::makeDisposition(
                disposition: HeaderUtils::DISPOSITION_ATTACHMENT,
                filename: $filename,
                filenameFallback: '' !== $filenameFallback ? $filenameFallback : 'download',
            ),
        ]);
    }
}
