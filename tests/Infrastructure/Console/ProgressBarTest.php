<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Console;

use App\Infrastructure\Console\ProgressBar;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;

class ProgressBarTest extends TestCase
{
    private BufferedOutput $output;

    public function testItRendersTheInitializingMessageOnStart(): void
    {
        new ProgressBar($this->output, 3)->start();

        $this->assertSame(
            '  0% [>---------------------------] 0/3 - Initializing...',
            $this->output->fetch(),
        );
    }

    public function testItRendersEveryStepWithItsOwnMessage(): void
    {
        $progressBar = new ProgressBar($this->output, 2);

        $progressBar->start();
        $progressBar->updateMessage('First step');
        $progressBar->advance();
        $progressBar->updateMessage('Second step');
        $progressBar->advance();

        $this->assertSame(
            implode("\n", [
                '  0% [>---------------------------] 0/2 - Initializing...',
                ' 50% [==============>-------------] 1/2 - First step',
                '100% [============================] 2/2 - Second step',
            ]),
            $this->output->fetch(),
        );
    }

    public function testFinishJumpsToTheEndClearsTheMessageAndWritesANewline(): void
    {
        $progressBar = new ProgressBar($this->output, 3);

        $progressBar->start();
        $progressBar->updateMessage('Halfway');
        $progressBar->advance();
        $progressBar->finish();

        $this->assertSame(
            implode("\n", [
                '  0% [>---------------------------] 0/3 - Initializing...',
                ' 33% [=========>------------------] 1/3 - Halfway',
                '100% [============================] 3/3 - ',
                '',
            ]),
            $this->output->fetch(),
        );
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->output = new BufferedOutput();
    }
}
