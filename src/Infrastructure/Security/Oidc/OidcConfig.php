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

    public function getClientSecret(): string
    {
        return $this->clientSecret;
    }

    /**
     * @param array<string, mixed> $claims
     */
    public function allows(array $claims): bool
    {
        $subject = $claims['sub'] ?? null;
        if (is_string($subject) && in_array($subject, $this->allowedUsers, true)) {
            return true;
        }

        $email = $claims['email'] ?? null;
        if (!is_string($email) || true !== ($claims['email_verified'] ?? null)) {
            return false;
        }

        return in_array(mb_strtolower($email), array_map(mb_strtolower(...), $this->allowedUsers), true);
    }
}
