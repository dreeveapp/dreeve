<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Fragment;

/**
 * Drives both the URL segment a fragment is served under and the response it is wrapped in,
 * so the two can never drift apart.
 */
enum FragmentType: string
{
    case PAGE = 'page';
    case DATA = 'data';
}
