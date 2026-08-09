<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Fragment;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.http_fragment')]
interface FragmentResolver
{
    public function resolve(string $path): ?ResolvedFragment;
}
