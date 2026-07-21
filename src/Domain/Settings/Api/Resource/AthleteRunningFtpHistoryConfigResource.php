<?php

declare(strict_types=1);

namespace App\Domain\Settings\Api\Resource;

use App\Domain\Ftp\FtpSport;

final readonly class AthleteRunningFtpHistoryConfigResource extends AthleteFtpHistoryConfigResource
{
    #[\Override]
    protected function sport(): FtpSport
    {
        return FtpSport::RUNNING;
    }
}
