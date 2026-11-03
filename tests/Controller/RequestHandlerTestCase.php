<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Tests\ContainerTestCase;
use Spatie\Snapshots\MatchesSnapshots;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

abstract class RequestHandlerTestCase extends ContainerTestCase
{
    use MatchesSnapshots;

    /**
     * @param array<string, mixed> $params
     */
    protected function buildRequest(array $params = [], ?string $content = null): Request
    {
        return new Request(
            query: $params,
            request: [],
            attributes: [],
            cookies: [],
            files: [],
            server: [],
            content: $content,
        );
    }

    protected function assertResponseMatchesJsonSnapshot(JsonResponse $response): void
    {
        $this->assertMatchesJsonSnapshot((string) $response->getContent());
    }
}
