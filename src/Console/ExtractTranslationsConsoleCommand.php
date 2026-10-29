<?php

declare(strict_types=1);

namespace App\Console;

use App\Infrastructure\Localisation\Locale;
use App\Infrastructure\ValueObject\String\KernelProjectDir;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Translation\Extractor\ExtractorInterface;
use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Component\Yaml\Yaml;

#[AsCommand(name: 'app:translations:extract', description: 'Extract translations for all locales')]
class ExtractTranslationsConsoleCommand extends Command
{
    public const array DOMAINS = ['messages', 'admin', 'metadata'];

    public function __construct(
        private readonly ExtractorInterface $extractor,
        private readonly KernelProjectDir $kernelProjectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('removeObsoleteTranslatables', null, InputOption::VALUE_NONE);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $messages = new MessageCatalogue(Locale::en_US->value);

        $this->extractor->extract($this->kernelProjectDir.'/templates', $messages);
        $this->extractor->extract($this->kernelProjectDir.'/src', $messages);

        foreach (self::DOMAINS as $domain) {
            $translatableKeys = array_keys($messages->all($domain));

            foreach (Locale::cases() as $locale) {
                $translationFilePath = sprintf('%s/translations/%s%s.%s.yaml', $this->kernelProjectDir, $domain, MessageCatalogue::INTL_DOMAIN_SUFFIX, $locale->value);
                $translations = file_exists($translationFilePath) ? Yaml::parse(file_get_contents($translationFilePath) ?: '') ?? [] : [];

                foreach ($translatableKeys as $translatableKey) {
                    $translations[$translatableKey] ??= $translatableKey;
                }

                if ($input->getOption('removeObsoleteTranslatables')) {
                    $translations = array_intersect_key($translations, array_flip($translatableKeys));
                }

                ksort($translations);
                file_put_contents($translationFilePath, Yaml::dump($translations));

                $output->writeln(sprintf('<info>Extracted "%s" translations for "%s"</info>', $domain, $locale->value));
            }
        }

        return Command::SUCCESS;
    }
}
