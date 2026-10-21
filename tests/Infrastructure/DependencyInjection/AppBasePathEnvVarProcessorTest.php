<?php

namespace App\Tests\Infrastructure\DependencyInjection;

use App\Infrastructure\DependencyInjection\AppBasePathEnvVarProcessor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AppBasePathEnvVarProcessorTest extends TestCase
{
    #[DataProvider('provideUrls')]
    public function testGetEnv(string $url, string $expected): void
    {
        $this->assertSame(
            $expected,
            new AppBasePathEnvVarProcessor()->getEnv('app_base_path', 'APP_URL', fn (string $name): string => $url)
        );
    }

    public static function provideUrls(): iterable
    {
        yield 'domain' => ['https://dreeve.example.com', '/'];
        yield 'domain with trailing slash' => ['http://localhost:8080/', '/'];
        yield 'sub-path' => ['https://example.com/dreeve', '/dreeve'];
        yield 'sub-path with trailing slash' => ['https://example.com/dreeve/', '/dreeve'];
        yield 'nested sub-path' => ['https://example.com/apps/dreeve/', '/apps/dreeve'];
        yield 'empty' => ['', '/'];
    }
}
