<?php

declare(strict_types=1);

namespace App\Domain\Settings;

final readonly class SecuritySettings
{
    private function __construct(
        private bool $requiresAuthentication,
        private bool $visitorsCanDownloadOriginalFiles,
    ) {
    }

    /**
     * @param array<string, mixed>|null $data
     */
    public static function fromArray(?array $data): self
    {
        $data ??= [];

        return new self(
            requiresAuthentication: filter_var($data['requiresAuthentication'] ?? false, FILTER_VALIDATE_BOOLEAN),
            visitorsCanDownloadOriginalFiles: filter_var($data['visitorsCanDownloadOriginalFiles'] ?? false, FILTER_VALIDATE_BOOLEAN),
        );
    }

    public function requiresAuthentication(): bool
    {
        return $this->requiresAuthentication;
    }

    public function visitorsCanDownloadOriginalFiles(): bool
    {
        return $this->visitorsCanDownloadOriginalFiles;
    }
}
