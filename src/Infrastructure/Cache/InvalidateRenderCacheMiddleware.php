<?php

declare(strict_types=1);

namespace App\Infrastructure\Cache;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;

final readonly class InvalidateRenderCacheMiddleware implements MiddlewareInterface
{
    public function __construct(
        private RenderCache $renderCache,
    ) {
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $command = $envelope->getMessage();
        $envelope = $stack->next()->handle($envelope, $stack);

        foreach (new \ReflectionClass($command)->getAttributes(InvalidatesCacheTags::class) as $attribute) {
            $this->renderCache->invalidateTags(...$attribute->newInstance()->cacheTags);
        }

        return $envelope;
    }
}
