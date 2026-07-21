<?php

declare(strict_types=1);

namespace App\Domain\Ftp;

enum FtpSport: string
{
    case CYCLING = 'cycling';
    case RUNNING = 'running';
}
