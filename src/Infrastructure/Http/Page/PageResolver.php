<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Page;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.html_page')]
interface PageResolver
{
    public function resolve(string $path): ?ResolvedPage;
}
