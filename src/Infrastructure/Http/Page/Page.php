<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Page;

use App\Infrastructure\Cache\Cacheable;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.html_page')]
interface Page extends Cacheable
{
    public function getPath(): string;
}
