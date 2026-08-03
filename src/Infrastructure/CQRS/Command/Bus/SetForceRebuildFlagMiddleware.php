<?php

declare(strict_types=1);

namespace App\Infrastructure\CQRS\Command\Bus;

use App\Infrastructure\CQRS\Command\RequiresRebuild;
use App\Infrastructure\KeyValue\Key;
use App\Infrastructure\KeyValue\KeyValue;
use App\Infrastructure\KeyValue\KeyValueStore;
use App\Infrastructure\KeyValue\Value;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;

final readonly class SetForceRebuildFlagMiddleware implements MiddlewareInterface
{
    public function __construct(
        private KeyValueStore $keyValueStore,
    ) {
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $command = $envelope->getMessage();
        $envelope = $stack->next()->handle($envelope, $stack);

        if ([] !== new \ReflectionClass($command)->getAttributes(RequiresRebuild::class)) {
            $this->keyValueStore->save(KeyValue::fromState(
                key: Key::FORCE_REBUILD,
                value: Value::fromString('1'),
            ));
        }

        return $envelope;
    }
}
