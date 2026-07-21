<?php

declare(strict_types=1);

namespace App\Domain\Settings\Api\Resource;

use App\Domain\Ftp\FtpSport;

final readonly class AthleteCyclingFtpHistoryConfigResource extends AthleteFtpHistoryConfigResource
{
    #[\Override]
    protected function sport(): FtpSport
    {
        return FtpSport::CYCLING;
    }
}
