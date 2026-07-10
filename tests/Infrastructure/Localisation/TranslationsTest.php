<?php

namespace App\Tests\Infrastructure\Localisation;

use App\Infrastructure\Localisation\Locale;
use App\Infrastructure\ValueObject\String\KernelProjectDir;
use App\Tests\ContainerTestCase;
use Spatie\Snapshots\MatchesSnapshots;
use Symfony\Component\Translation\Extractor\ExtractorInterface;
use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Component\Translation\MessageCatalogueInterface;
use Symfony\Component\Yaml\Yaml;

class TranslationsTest extends ContainerTestCase
{
    use MatchesSnapshots;

    private KernelProjectDir $kernelProjectDir;
    private ExtractorInterface $extractor;

    private const array DOMAINS = ['messages', 'admin'];

    public function testAllTranslationsHaveBeenExtracted(): void
    {
        $catalogue = new MessageCatalogue(Locale::en_US->value);
        $this->extractor->extract($this->kernelProjectDir.'/templates', $catalogue);
        $this->extractor->extract($this->kernelProjectDir.'/src', $catalogue);

        $messages = [];
        foreach (self::DOMAINS as $domain) {
            $translatableKeys = array_keys($catalogue->all()[$domain] ?? []);

            foreach (Locale::cases() as $locale) {
                $translationFilePath = sprintf('%s/translations/%s%s.%s.yaml', $this->kernelProjectDir, $domain, MessageCatalogue::INTL_DOMAIN_SUFFIX, $locale->value);
                if (!file_exists($translationFilePath)) {
                    $this->fail(sprintf('Not all translations for locale %s have been exported. Please run "make translation-extract"', $locale->value));
                }

                $parsedTranslations = Yaml::parse(file_get_contents($translationFilePath)) ?? [];

                $missingTranslationKeys = array_diff($translatableKeys, array_keys($parsedTranslations));
                $extraTranslationKeys = array_diff(array_keys($parsedTranslations), $translatableKeys);

                if ([] !== $missingTranslationKeys || [] !== $extraTranslationKeys) {
                    $messages[] = sprintf("Translation mismatch for domain '%s', locale '%s':\n", $domain, $locale->value);

                    if ([] !== $missingTranslationKeys) {
                        $messages[] = " Missing keys:\n  - ".implode("\n  - ", $missingTranslationKeys)."\n";
                    }
                    if ([] !== $extraTranslationKeys) {
                        $messages[] = " Extra keys:\n  - ".implode("\n  - ", $extraTranslationKeys)."\n";
                    }
                }
            }
        }

        if ([] !== $messages) {
            $messages[] = 'Run: make translation-extract';
            $this->fail(implode(PHP_EOL, $messages));
        }
        $this->addToAssertionCount(1);
    }

    public function testTranslationsContainPlaceholders(): void
    {
        foreach (self::DOMAINS as $domain) {
            foreach (Locale::cases() as $locale) {
                $translationFilePath = sprintf('%s/translations/%s%s.%s.yaml', $this->kernelProjectDir, $domain, MessageCatalogueInterface::INTL_DOMAIN_SUFFIX, $locale->value);
                if (!file_exists($translationFilePath)) {
                    continue;
                }

                $parsedTranslations = Yaml::parse(file_get_contents($translationFilePath)) ?? [];
                foreach ($parsedTranslations as $key => $translation) {
                    if (!preg_match_all('/[\s\S]*\{(?<matches>[\S]*)\}[\s\S]*/U', (string) $key, $translationPlaceholdersInKeys)) {
                        continue;
                    }

                    if (!preg_match_all('/[\s\S]*\{(?<matches>[\S]*)\}[\s\S]*/U', (string) $translation, $translationPlaceholdersInTranslations)) {
                        $this->fail(sprintf('The translation "%s" does not contain all placeholders.', $translation));
                    }

                    $this->assertEqualsCanonicalizing(
                        $translationPlaceholdersInKeys['matches'],
                        $translationPlaceholdersInTranslations['matches'],
                        sprintf('The translation "%s" does not contain all placeholders.', $translation)
                    );
                }
            }
        }
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->kernelProjectDir = $this->getContainer()->get(KernelProjectDir::class);
        $this->extractor = $this->getContainer()->get(ExtractorInterface::class);
    }
}
