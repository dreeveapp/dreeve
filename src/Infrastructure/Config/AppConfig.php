<?php

declare(strict_types=1);

namespace App\Infrastructure\Config;

use App\Infrastructure\ValueObject\String\KernelProjectDir;
use Symfony\Component\Finder\Exception\DirectoryNotFoundException;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

final class AppConfig
{
    /** @var array<string|int, string|int|float|array<string|int,mixed>|null> */
    private array $config = [];

    /** @var array<string> */
    private static array $yamlConfigFiles = [];

    public function __construct()
    {
        $this->buildConfig();
    }

    /**
     * @return string|int|float|array<string|int,mixed>|bool|null
     */
    public function get(string $key, mixed $default = null): string|int|float|array|bool|null
    {
        if (!array_key_exists($key, $this->config)) {
            if (null !== $default) {
                return $default;
            }

            throw new \RuntimeException(sprintf('Unknown configuration key "%s"', $key));
        }

        return $this->config[$key];
    }

    public static function setYamlConfigFilesToParse(KernelProjectDir $kernelProjectDir, PlatformEnvironment $platformEnvironment): void
    {
        $basePath = $platformEnvironment->isTest() ? $kernelProjectDir.'/config/app/test' : $kernelProjectDir.'/config/app';

        $mainConfigFile = $basePath.'/config.yaml';
        if (!file_exists($mainConfigFile)) {
            throw CouldNotParseYamlConfig::configFileNotFound();
        }
        self::$yamlConfigFiles = [$mainConfigFile];

        try {
            $finder = Finder::create()
                ->in($basePath)
                ->depth('== 0')
                ->files()
                ->sortByName()
                ->name('config-*.yaml');

            foreach ($finder as $file) {
                self::$yamlConfigFiles[] = $file->getRealPath();
            }
        } catch (DirectoryNotFoundException) { // @codeCoverageIgnore
        }
    }

    private function buildConfig(): void
    {
        $this->config = [];
        $parsedYaml = [];

        if ([] === self::$yamlConfigFiles) {
            throw new \RuntimeException('No YAML config files processed yet. AppConfig::setYamlConfigFilesToParse() must be called first.'); // @codeCoverageIgnore
        }

        foreach (self::$yamlConfigFiles as $filePath) {
            try {
                $parsedYaml = array_replace_recursive($parsedYaml, Yaml::parseFile($filePath));
            } catch (ParseException $e) {
                throw CouldNotParseYamlConfig::invalidYml($e->getMessage());
            }
        }

        $this->processYamlConfig(
            ymlConfig: $parsedYaml,
            prefix: null
        );
    }

    /**
     * @param array<string|int, mixed> $ymlConfig
     */
    private function processYamlConfig(array $ymlConfig, ?string $prefix): void
    {
        foreach ($ymlConfig as $key => $value) {
            if (is_string($key) && str_contains($key, '_')) {
                // This key is in snake_case, convert it to camelCase to make sure this stays backwards compatible
                $key = lcfirst(\str_replace('_', '', \ucwords($key, '_')));
            }

            $fullKey = (string) (null === $prefix ? $key : "$prefix.$key");
            if (array_key_exists($fullKey, $this->config)) {
                throw new CouldNotParseYamlConfig(sprintf('Duplicate config key: %s', $fullKey)); // @codeCoverageIgnore
            }
            $this->config[$fullKey] = $value;

            if (is_array($value)) {
                $this->processYamlConfig(
                    ymlConfig: $value,
                    prefix: $fullKey
                );
            }
        }
    }
}
