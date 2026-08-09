<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Fragment;

use App\Infrastructure\Cache\Cacheable;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.http_fragment')]
interface Fragment extends Cacheable
{
    public function getPath(): string;

    public function getType(): FragmentType;
}
