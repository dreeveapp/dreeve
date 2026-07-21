<?php

declare(strict_types=1);

namespace App\Domain\Settings\Api;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * A single addressable piece of configuration, exposed over the HTTP API.
 *
 * Implement this interface to add a new endpoint: the class is picked up
 * automatically through the "app.api.config_resource" tag and becomes
 * readable at /api/v1/config/{name}. No controller or routing changes needed.
 *
 * This interface is read-only. Implement WritableConfigResource as well to
 * accept PUT.
 */
#[AutoconfigureTag('app.api.config_resource')]
interface ConfigResource
{
    /**
     * Path segment this resource is addressable under, relative to
     * /api/v1/config. May contain slashes, e.g. "athlete/weight-history".
     */
    public function getName(): string;

    /**
     * Current value, as returned by GET and echoed back by a successful PUT.
     *
     * Must not expose secrets: everything returned here is visible to any
     * client holding an API token.
     *
     * @return array<string, mixed>
     */
    public function read(): array;
}
