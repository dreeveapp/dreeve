<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Page;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final readonly class PageRegistry
{
    /**
     * @param iterable<Page> $pages
     */
    public function __construct(
        #[AutowireIterator('app.html_page')]
        private iterable $pages,
    ) {
    }

    public function find(string $path): ?Page
    {
        foreach ($this->pages as $page) {
            if ($page instanceof PageWithParameters) {
                if ($resolvedPage = $page->resolve($path)) {
                    return $resolvedPage;
                }
                continue;
            }

            if ($page->getPath() === $path) {
                return $page;
            }
        }

        return null;
    }

    /**
     * @return iterable<Page>
     */
    public function all(): iterable
    {
        return $this->pages;
    }
}
