<?php

declare(strict_types=1);

namespace App\Domain\Settings;

use App\Domain\Ftp\FtpHistory;

final readonly class AthleteFtpSettings
{
    private function __construct(
        private FtpHistory $ftpHistory,
    ) {
    }

    /**
     * @param array<string, mixed>|null $data
     */
    public static function fromArray(?array $data): self
    {
        return new self(FtpHistory::fromArray($data['ftpHistory'] ?? []));
    }

    public function getHistory(): FtpHistory
    {
        return $this->ftpHistory;
    }
}
