<?php

declare(strict_types=1);

namespace App\Domain\Settings\Api;

use App\Infrastructure\CQRS\Command\Command;

/**
 * A ConfigResource that also accepts PUT.
 *
 * Kept separate from ConfigResource so a resource can be exposed read-only,
 * for instance one that reports state a client should not be able to change,
 * or a redacted view of settings that hold secrets.
 */
interface WritableConfigResource extends ConfigResource
{
    /**
     * Validate an incoming PUT body and turn it into a command.
     *
     * Implementations must not mutate anything: the returned command is
     * dispatched by the caller, so validation failures surface as a 400 before
     * any write happens.
     *
     * @param array<string, mixed> $payload
     *
     * @throws \App\Infrastructure\CQRS\Command\Deserialize\CouldNotDeserializeCommand
     */
    public function buildUpdateCommand(array $payload): Command;
}
