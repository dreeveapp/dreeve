<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Fragment;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final readonly class FragmentRegistry
{
    /**
     * @param iterable<Fragment|FragmentResolver> $fragments
     */
    public function __construct(
        #[AutowireIterator('app.http_fragment')]
        private iterable $fragments,
    ) {
    }

    public function find(string $path): ?Fragment
    {
        foreach ($this->fragments as $fragment) {
            if ($fragment instanceof FragmentResolver) {
                if ($resolvedFragment = $fragment->resolve($path)) {
                    return $resolvedFragment;
                }
                continue;
            }

            if ($fragment->getPath() === $path) {
                return $fragment;
            }
        }

        return null;
    }

    public function findOfType(string $path, FragmentType $type): ?Fragment
    {
        $fragment = $this->find($path);

        return $fragment?->getType() === $type ? $fragment : null;
    }

    /**
     * @return iterable<Fragment|FragmentResolver>
     */
    public function all(): iterable
    {
        return $this->fragments;
    }
}
