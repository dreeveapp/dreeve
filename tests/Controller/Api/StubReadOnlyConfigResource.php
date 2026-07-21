<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api;

use App\Domain\Settings\Api\ConfigResource;

/**
 * Every shipped resource is writable, so this stub exists to cover the
 * read-only branch of the API: advertising GET only, and refusing PUT.
 */
final readonly class StubReadOnlyConfigResource implements ConfigResource
{
    #[\Override]
    public function getName(): string
    {
        return 'stub/read-only';
    }

    #[\Override]
    public function read(): array
    {
        return ['stub' => true];
    }
}
