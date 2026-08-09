<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Fragment;

enum FragmentType: string
{
    case PAGE = 'page';
    case PARTIAL = 'partial';
    case DATA = 'data';
}
