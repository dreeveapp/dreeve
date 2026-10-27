<?php

namespace App\Tests\Caddy;

use GuzzleHttp\Client;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

class CaddyfileFileAccessTest extends TestCase
{
    private const string PATHS_SNIPPET = __DIR__.'/../../docker/app/config/snippets/paths.caddyfile';
    private const string TEST_CADDYFILE = __DIR__.'/Caddyfile.test';
    private const string FIXTURES = __DIR__.'/fixtures';
    private const int PORT = 8390;

    private static string $workDir;
    private static ?Process $caddy = null;
    private static Client $http;

    public function testAssetsAreServedAndCached(): void
    {
        $response = self::$http->get('/assets/app.css');

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('max-age=86400', $response->getHeaderLine('Cache-Control'));
    }

    #[DataProvider('provideFilesNotServedByCaddy')]
    public function testFilesAreNotServedByCaddy(string $path): void
    {
        $response = self::$http->get($path);

        self::assertSame(404, $response->getStatusCode(), sprintf('Unexpected status code for "%s"', $path));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideFilesNotServedByCaddy(): iterable
    {
        yield 'top-level image' => ['/files/sample.png'];
        yield 'nested image' => ['/files/challenges/nested.png'];
        yield 'uppercase extension' => ['/files/UPPER.PNG'];
        yield 'activity photo' => ['/files/activities/secret.jpg'];
        yield 'nested activity photo' => ['/files/activities/2024/secret.jpg'];
        yield 'non-image file' => ['/files/secret.txt'];
        yield 'log file' => ['/files/strava.log'];
    }

    public static function setUpBeforeClass(): void
    {
        self::$workDir = sys_get_temp_dir().'/sfs-caddy-test-'.bin2hex(random_bytes(5));
        mkdir(self::$workDir, 0o777, true);

        $fixtures = realpath(self::FIXTURES);
        if (false === $fixtures) {
            throw new \RuntimeException(sprintf('Could not locate the fixtures directory at "%s"', self::FIXTURES));
        }

        self::$caddy = new Process(
            ['caddy', 'run', '--config', self::TEST_CADDYFILE, '--adapter', 'caddyfile'],
            env: [
                'SFS_TEST_PORT' => (string) self::PORT,
                'SFS_TEST_FIXTURES' => $fixtures,
                'SFS_TEST_SNIPPET' => self::writeTestSnippet($fixtures),
            ],
        );
        self::$caddy->start();
        self::waitUntilListening(self::PORT);

        self::$http = new Client([
            'base_uri' => sprintf('http://127.0.0.1:%d', self::PORT),
            'http_errors' => false,
            'allow_redirects' => false,
            'timeout' => 5,
        ]);
    }

    public static function tearDownAfterClass(): void
    {
        self::$caddy?->stop();
        new Filesystem()->remove(self::$workDir);
    }

    private static function writeTestSnippet(string $fixtures): string
    {
        $snippet = file_get_contents(self::PATHS_SNIPPET);
        if (false === $snippet) {
            throw new \RuntimeException(sprintf('Could not read the paths snippet at "%s"', self::PATHS_SNIPPET));
        }

        $testSnippet = self::$workDir.'/paths.caddyfile';
        file_put_contents($testSnippet, str_replace('/var/www', $fixtures, $snippet));

        return $testSnippet;
    }

    private static function waitUntilListening(int $port): void
    {
        for ($attempt = 0; $attempt < 100; ++$attempt) {
            $connection = @stream_socket_client(sprintf('tcp://127.0.0.1:%d', $port), $errno, $errstr, 0.1);
            if (false !== $connection) {
                fclose($connection);

                return;
            }
            usleep(50_000);
        }

        throw new \RuntimeException(sprintf('Caddy did not start listening on port %d. Output: %s', $port, self::$caddy?->getErrorOutput() ?? ''));
    }
}
