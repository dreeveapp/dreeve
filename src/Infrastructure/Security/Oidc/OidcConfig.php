<?php

declare(strict_types=1);

namespace App\Infrastructure\Security\Oidc;

use App\Infrastructure\ValueObject\String\Url;

final readonly class OidcConfig
{
    /**
     * @param string[] $allowedUsers
     */
    private function __construct(
        private ?Url $issuerUrl,
        private string $clientId,
        private string $clientSecret,
        private array $allowedUsers,
    ) {
    }

    public static function fromStrings(string $issuerUrl, string $clientId, string $clientSecret, string $allowedUsers): self
    {
        return new self(
            issuerUrl: Url::fromOptionalString($issuerUrl),
            clientId: trim($clientId),
            clientSecret: trim($clientSecret),
            allowedUsers: array_values(array_filter(array_map(trim(...), explode(',', $allowedUsers)))),
        );
    }

    public function isEnabled(): bool
    {
        return null !== $this->issuerUrl
            && '' !== $this->clientId
            && '' !== $this->clientSecret
            && [] !== $this->allowedUsers;
    }

    public function getIssuerUrl(): ?Url
    {
        return $this->issuerUrl;
    }

    public function getClientId(): string
    {
        return $this->clientId;
    }
}
