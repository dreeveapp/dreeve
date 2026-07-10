<?php

declare(strict_types=1);

namespace App\Console;

use App\Infrastructure\Console\ConsoleApplication;
use App\Infrastructure\Localisation\Locale;
use App\Infrastructure\ValueObject\String\KernelProjectDir;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Translation\Extractor\ExtractorInterface;
use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Component\Yaml\Yaml;

#[AsCommand(name: 'app:translations:extract', description: 'Extract translations for all locales')]
class ExtractTranslationsConsoleCommand extends Command
{
    private const array DOMAINS = ['messages', 'admin'];

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
        foreach (self::DOMAINS as $domain) {
            foreach (Locale::cases() as $locale) {
                $arrayInput = new ArrayInput([
                    'command' => 'translation:extract',
                    '--force' => true,
                    '--prefix' => '',
                    '--domain' => $domain,
                    '--format' => 'yaml',
                    '--sort' => 'ASC',
                    'locale' => $locale->value,
                ]);
                $arrayInput->setInteractive(false);
                ConsoleApplication::get()->doRun(
                    input: $arrayInput,
                    output: new NullOutput(),
                );

                $output->writeln(sprintf('<info>Extracted "%s" translations for "%s"</info>', $domain, $locale->value));
            }
        }

        if (!$input->getOption('removeObsoleteTranslatables')) {
            return Command::SUCCESS;
        }

        $messages = new MessageCatalogue(Locale::en_US->value);

        $this->extractor->extract($this->kernelProjectDir.'/templates', $messages);
        $this->extractor->extract($this->kernelProjectDir.'/src', $messages);

        foreach (self::DOMAINS as $domain) {
            $translatables = $messages->all()[$domain] ?? [];
            $translatableKeys = array_keys($translatables);

            $translationFilePath = sprintf('%s/translations/%s%s.%s.yaml', $this->kernelProjectDir, $domain, MessageCatalogue::INTL_DOMAIN_SUFFIX, Locale::en_US->value);
            $parsedTranslations = Yaml::parse(file_get_contents($translationFilePath) ?: '') ?? [];

            if (!$translationKeysToRemove = array_diff(array_keys($parsedTranslations), $translatableKeys)) {
                continue;
            }

            foreach (Locale::cases() as $locale) {
                $translationFilePath = sprintf('%s/translations/%s%s.%s.yaml', $this->kernelProjectDir, $domain, MessageCatalogue::INTL_DOMAIN_SUFFIX, $locale->value);
                $parsedTranslations = Yaml::parse(file_get_contents($translationFilePath) ?: '') ?? [];

                foreach ($translationKeysToRemove as $keyToRemove) {
                    unset($parsedTranslations[$keyToRemove]);
                }

                file_put_contents($translationFilePath, Yaml::dump($parsedTranslations));
            }
        }

        return Command::SUCCESS;
    }
}
