<?php

namespace Idei\Usim\Sync\Concerns;

use Illuminate\Console\Command;
use Illuminate\Console\OutputStyle;

/**
 * Trait providing consistent console output formatting for sync handlers.
 */
trait WritesSyncOutput
{
    protected function writeInfo(Command|OutputStyle|null $output, string $message): void
    {
        if ($output instanceof Command) {
            $output->info($message);
        } elseif ($output !== null) {
            $output->writeln("<info>{$message}</info>");
        }
    }

    protected function writeWarn(Command|OutputStyle|null $output, string $message): void
    {
        if ($output instanceof Command) {
            $output->warn($message);
        } elseif ($output !== null) {
            $output->writeln("<comment>{$message}</comment>");
        }
    }

    protected function writeLine(Command|OutputStyle|null $output, string $message): void
    {
        if ($output instanceof Command) {
            $output->line($message);
        } elseif ($output !== null) {
            $output->writeln($message);
        }
    }

    protected function writeNewLine(Command|OutputStyle|null $output, int $count = 1): void
    {
        if ($output !== null) {
            $output->newLine($count);
        }
    }
}
