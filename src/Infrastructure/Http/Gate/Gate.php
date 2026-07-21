<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Gate;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\HttpFoundation\Request;

#[AutoconfigureTag('app.http.gate')]
interface Gate
{
    /**
     * Requests under this prefix are served by the configuration API, which
     * answers machine clients and must not be redirected into the setup flow.
     */
    public const string API_PATH_PREFIX = '/api/v1/';

    public function handle(Request $request): GateDecision;
}
