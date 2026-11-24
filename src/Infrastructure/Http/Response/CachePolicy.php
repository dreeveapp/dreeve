<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Response;

use Symfony\Component\HttpFoundation\Response;
use Time\Duration;

final readonly class CachePolicy
{
    private function __construct(
        private bool $isPublic,
        private bool $noStore = false,
        private bool $revalidate = false,
        private ?Duration $maxAge = null,
        private bool $immutable = false,
    ) {
    }

    public static function private(): self
    {
        return new self(isPublic: false);
    }

    public static function public(): self
    {
        return new self(isPublic: true);
    }

    public function noStore(): self
    {
        return clone ($this, ['noStore' => true]);
    }

    public function revalidate(): self
    {
        return clone ($this, ['revalidate' => true]);
    }

    public function maxAge(Duration $maxAge): self
    {
        return clone ($this, ['maxAge' => $maxAge]);
    }

    public function immutable(): self
    {
        return clone ($this, ['immutable' => true]);
    }

    /**
     * @template T of Response
     *
     * @param T $response
     *
     * @return T
     */
    public function applyTo(Response $response): Response
    {
        $response->setCache([
            'public' => $this->isPublic,
            'no_store' => $this->noStore,
            'no_cache' => $this->revalidate,
            'max_age' => $this->maxAge?->seconds,
            'immutable' => $this->immutable,
        ]);

        if ($this->revalidate) {
            $response->setEtag(hash('xxh128', (string) $response->getContent()));
        }

        return $response;
    }
}
