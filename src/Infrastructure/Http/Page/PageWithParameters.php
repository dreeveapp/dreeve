<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Page;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.html_page')]
interface PageWithParameters extends Page
{
    public function resolve(string $path): ?self;
}
