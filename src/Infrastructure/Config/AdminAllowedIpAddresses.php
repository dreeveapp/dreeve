<?php

declare(strict_types=1);

namespace App\Infrastructure\Config;

use Symfony\Component\HttpFoundation\IpUtils;

final readonly class AdminAllowedIpAddresses implements \Stringable
{
    /**
     * @param string[] $ipAddresses
     */
    private function __construct(
        private array $ipAddresses,
    ) {
    }

    public static function fromString(string $string): self
    {
        $ipAddresses = array_values(array_filter(array_map(
            trim(...),
            explode(',', $string)
        )));

        foreach ($ipAddresses as $ipAddress) {
            if (!self::isValidIpOrCidr($ipAddress)) {
                throw new \InvalidArgumentException(sprintf('"%s" is not a valid IP address', $ipAddress));
            }
        }

        return new self($ipAddresses);
    }

    public function contains(?string $ipAddress): bool
    {
        return null !== $ipAddress && IpUtils::checkIp($ipAddress, $this->ipAddresses);
    }

    public function isEmpty(): bool
    {
        return [] === $this->ipAddresses;
    }

    public function __toString(): string
    {
        return implode(', ', $this->ipAddresses);
    }

    private static function isValidIpOrCidr(string $entry): bool
    {
        if (!str_contains($entry, '/')) {
            return false !== filter_var($entry, FILTER_VALIDATE_IP);
        }

        [$address, $prefix] = explode('/', $entry, 2);

        if (false === filter_var($address, FILTER_VALIDATE_IP)) {
            return false;
        }

        if ('' === $prefix || !ctype_digit($prefix)) {
            return false;
        }

        $isIpv4 = false !== filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
        $maxPrefix = $isIpv4 ? 32 : 128;

        return (int) $prefix <= $maxPrefix;
    }
}
