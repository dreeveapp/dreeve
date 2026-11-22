<?php

declare(strict_types=1);

namespace App\Infrastructure\Daemon\Process;

enum DaemonProcessType: string
{
    case CRON_ACTION = 'cron action';
    case STARTUP_TASK = 'startup task';
}
