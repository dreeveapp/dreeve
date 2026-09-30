<?php

namespace App\Tests\Infrastructure\Http\Request;

use App\Domain\Activity\ActivityId;
use Symfony\Component\HttpFoundation\Response;

final readonly class IdentifierControllerStub
{
    public function handle(ActivityId $activityId): Response
    {
        return new Response((string) $activityId);
    }
}
