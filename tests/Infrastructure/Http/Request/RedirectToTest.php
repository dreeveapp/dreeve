<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Http\Request;

use App\Application\AppUrl;
use App\Infrastructure\Http\Request\RedirectTo;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

class RedirectToTest extends TestCase
{
    #[DataProvider('provideUrlsThatLeaveTheApp')]
    public function testItRejectsAnythingThatCouldNavigateOffSite(string $url): void
    {
        $this->assertNull(RedirectTo::fromRequest(
            new Request(query: [RedirectTo::QUERY_PARAM => $url]),
            AppUrl::fromString('https://example.com/')
        ));
    }

    #[DataProvider('provideUrlsWithinTheApp')]
    public function testItAcceptsAPathWithinTheApp(string $url, string $expected): void
    {
        $this->assertSame($expected, (string) RedirectTo::fromRequest(
            new Request(query: [RedirectTo::QUERY_PARAM => $url]),
            AppUrl::fromString('https://example.com/')
        ));
    }

    public function testItIsAbsentWhenTheQueryParamIsNotGiven(): void
    {
        $this->assertNull(RedirectTo::fromRequest(new Request(), AppUrl::fromString('https://example.com/')));
    }

    public function testItIsAbsentWhenTheQueryParamIsNotAString(): void
    {
        $this->assertNull(RedirectTo::fromRequest(
            new Request(query: [RedirectTo::QUERY_PARAM => ['/activities/1']]),
            AppUrl::fromString('https://example.com/')
        ));
    }

    public function testItRejectsAPathOutsideTheConfiguredBasePath(): void
    {
        $this->assertNull(RedirectTo::fromRequest(
            new Request(query: [RedirectTo::QUERY_PARAM => '/activities/1']),
            AppUrl::fromString('https://example.com/dreeve/')
        ));
    }

    public function testItAcceptsAPathWithinTheConfiguredBasePath(): void
    {
        $this->assertSame(
            '/dreeve/activities/1',
            (string) RedirectTo::fromRequest(
                new Request(query: [RedirectTo::QUERY_PARAM => '/dreeve/activities/1']),
                AppUrl::fromString('https://example.com/dreeve/')
            )
        );
    }

    public function testItRejectsADifferentPortOnTheSameHost(): void
    {
        $this->assertNull(RedirectTo::fromRequest(
            new Request(query: [RedirectTo::QUERY_PARAM => 'https://example.com:8443/activities/1']),
            AppUrl::fromString('https://example.com/')
        ));
    }

    public static function provideUrlsThatLeaveTheApp(): \Generator
    {
        yield 'absolute url' => ['https://evil.com'];
        yield 'javascript uri' => ['javascript:alert(1)'];
        yield 'protocol relative' => ['//evil.com'];
        yield 'backslash protocol relative' => ['/\evil.com'];
        yield 'mixed slashes' => ['\/\/evil.com'];
        yield 'tab hides a protocol relative url' => ["/\t/evil.com"];
        yield 'empty' => [''];
        yield 'blank' => ['   '];
    }

    public static function provideUrlsWithinTheApp(): \Generator
    {
        yield 'root' => ['/', '/'];
        yield 'plain path' => ['/activities/activity-1', '/activities/activity-1'];
        yield 'with query string' => ['/activities?filters[isCommute]=true', '/activities?filters[isCommute]=true'];
        yield 'with fragment' => ['/activities/activity-1#splits', '/activities/activity-1#splits'];
        yield 'relative path' => ['activities/activity-1', '/activities/activity-1'];
        yield 'traversal above the root' => ['/activities/../../etc/passwd', '/etc/passwd'];
        yield 'embedded newline is stripped' => ["/activities/1\n/evil", '/activities/1/evil'];
    }
}
