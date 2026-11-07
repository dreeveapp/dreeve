<?php

declare(strict_types=1);

namespace App\Console\Release;

use App\Application\AppVersion;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:release:verify-version', description: 'Verify the app version matches the release tag', hidden: true)]
final class VerifyAppVersionConsoleCommand extends Command
{
    private const string TAG_ARGUMENT = 'tag';

    protected function configure(): void
    {
        $this->addArgument(self::TAG_ARGUMENT, InputArgument::REQUIRED, 'The git tag of the release');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output = new SymfonyStyle($input, $output);
        $tag = $input->getArgument(self::TAG_ARGUMENT);
        $appVersion = AppVersion::getSemanticVersion();

        if ($appVersion !== $tag) {
            $output->error(sprintf('AppVersion is %s but the tag is %s, bump src/Application/AppVersion.php', $appVersion, $tag));

            return Command::FAILURE;
        }

        $output->writeln(sprintf('AppVersion %s matches the tag', $appVersion));

        return Command::SUCCESS;
    }
}
