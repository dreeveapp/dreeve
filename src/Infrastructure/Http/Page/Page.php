<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Page;

use App\Infrastructure\Cache\Cacheable;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.html_page')]
interface Page extends Cacheable
{
    /**
     * The path this page is served at: 'photos' is served at /api/photos and prunes build/html/photos.html.
     */
    public function getPath(): string;
}
