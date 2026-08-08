<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Page;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * A page that serves a whole sub tree of paths instead of one fixed path.
 */
#[AutoconfigureTag('app.html_page')]
interface DynamicPage extends Page
{
    /**
     * Returns an instance bound to the given path, or null when this page does not serve it.
     */
    public function resolve(string $path): ?self;
}
