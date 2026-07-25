<?php

namespace App\Tests\Infrastructure\Config;

use App\Infrastructure\Config\AdminAllowedIpAddresses;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AdminAllowedIpAddressesTest extends TestCase
{
    #[DataProvider('provideContainsExpectations')]
    public function testContains(string $string, ?string $ipAddress, bool $expectedResult): void
    {
        $this->assertSame($expectedResult, AdminAllowedIpAddresses::fromString($string)->contains($ipAddress));
    }

    public static function provideContainsExpectations(): iterable
    {
        yield 'contains first listed ip address' => ['192.168.1.1,10.0.0.1', '192.168.1.1', true];
        yield 'contains second listed ip address' => ['192.168.1.1,10.0.0.1', '10.0.0.1', true];
        yield 'does not contain unlisted ip address' => ['192.168.1.1,10.0.0.1', '10.0.0.2', false];
        yield 'does not contain null ip address' => ['192.168.1.1', null, false];
        yield 'trims whitespace around entries, first entry' => ['  192.168.1.1 ,  10.0.0.1  ', '192.168.1.1', true];
        yield 'trims whitespace around entries, second entry' => ['  192.168.1.1 ,  10.0.0.1  ', '10.0.0.1', true];
        yield 'filters out empty entries, first entry' => ['192.168.1.1,,, ,10.0.0.1,', '192.168.1.1', true];
        yield 'filters out empty entries, second entry' => ['192.168.1.1,,, ,10.0.0.1,', '10.0.0.1', true];
        yield 'supports ipv6, exact match' => ['2001:db8::1', '2001:db8::1', true];
        yield 'supports ipv6, different address' => ['2001:db8::1', '2001:db8::2', false];
        yield 'ipv6 cidr range, laptop on the same /64' => ['2a02:a03f:e02e:c301::/64', '2a02:a03f:e02e:c301:d94e:9dce:b47e:2a47', true];
        yield 'ipv6 cidr range, phone on the same /64' => ['2a02:a03f:e02e:c301::/64', '2a02:a03f:e02e:c301:1111:2222:3333:4444', true];
        yield 'ipv6 cidr range, a different /64 prefix is not trusted' => ['2a02:a03f:e02e:c301::/64', '2a02:a03f:e02e:c302:d94e:9dce:b47e:2a47', false];
        yield 'ipv4 cidr range, first address in range' => ['192.168.1.0/24', '192.168.1.1', true];
        yield 'ipv4 cidr range, last address in range' => ['192.168.1.0/24', '192.168.1.254', true];
        yield 'ipv4 cidr range, address outside range' => ['192.168.1.0/24', '192.168.2.1', false];
        yield 'mixed plain and cidr, plain address' => ['10.0.0.5, 192.168.1.0/24', '10.0.0.5', true];
        yield 'mixed plain and cidr, address within range' => ['10.0.0.5, 192.168.1.0/24', '192.168.1.42', true];
        yield 'mixed plain and cidr, unlisted address' => ['10.0.0.5, 192.168.1.0/24', '10.0.0.6', false];
    }

    public function testFiltersOutEmptyEntries(): void
    {
        $this->assertFalse(AdminAllowedIpAddresses::fromString('192.168.1.1,,, ,10.0.0.1,')->isEmpty());
    }

    public function testIsNotEmptyWhenPopulated(): void
    {
        $this->assertFalse(AdminAllowedIpAddresses::fromString('192.168.1.1')->isEmpty());
    }

    #[DataProvider('provideEmptyStrings')]
    public function testIsEmpty(string $string): void
    {
        $allowed = AdminAllowedIpAddresses::fromString($string);

        $this->assertTrue($allowed->isEmpty());
        $this->assertFalse($allowed->contains('192.168.1.1'));
        $this->assertFalse($allowed->contains(null));
    }

    public static function provideEmptyStrings(): iterable
    {
        yield 'empty string' => [''];
        yield 'whitespace only' => ['   '];
        yield 'only commas' => [',,,'];
        yield 'commas and whitespace' => [' , , '];
    }

    #[DataProvider('provideInvalidIpAddresses')]
    public function testItShouldThrowOnInvalidIpAddress(string $string, string $invalidValue): void
    {
        $this->expectExceptionObject(new \InvalidArgumentException(sprintf('"%s" is not a valid IP address', $invalidValue)));

        AdminAllowedIpAddresses::fromString($string);
    }

    public static function provideInvalidIpAddresses(): iterable
    {
        yield 'not an ip' => ['not-an-ip', 'not-an-ip'];
        yield 'incomplete ipv4' => ['192.168.1', '192.168.1'];
        yield 'octet out of range' => ['999.999.999.999', '999.999.999.999'];
        yield 'invalid among valid' => ['192.168.1.1,nope', 'nope'];
        yield 'cidr with invalid address' => ['nope/24', 'nope/24'];
        yield 'cidr without prefix' => ['192.168.1.0/', '192.168.1.0/'];
        yield 'cidr with non-numeric prefix' => ['192.168.1.0/ab', '192.168.1.0/ab'];
        yield 'ipv4 prefix out of range' => ['192.168.1.0/33', '192.168.1.0/33'];
        yield 'ipv6 prefix out of range' => ['2a02:a03f:e02e:c301::/129', '2a02:a03f:e02e:c301::/129'];
    }
}
