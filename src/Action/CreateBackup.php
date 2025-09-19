<?php

declare(strict_types=1);

namespace Terminal42\Restic\Action;

use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Terminal42\Restic\Action\Result\CreateBackupResult;

class CreateBackup extends AbstractAction
{
    private CreateBackupResult $result;

    private array $summary = [];

    public function __construct(
        private readonly array $itemsToBackup,
        private readonly array $excludes,
        private readonly bool $noScan,
        private readonly bool $dryRun,
        private readonly \DateTimeInterface|null $time = null,
    ) {
    }

    public function getResult(): CreateBackupResult
    {
        return $this->result;
    }

    protected function doGetArguments(): array
    {
        $arguments = [
            'backup',
            '--json',
        ];

        if ($this->noScan) {
            $arguments[] = '--no-scan';
        }

        if ($this->dryRun) {
            $arguments[] = '--dry-run';
        }

        foreach ($this->excludes as $exclude) {
            $arguments[] = '--exclude';
            $arguments[] = $exclude;
        }

        if (null !== $this->time) {
            $arguments[] = '--time';
            $arguments[] = $this->time->format('Y-m-d H:i:s');
        }

        return array_merge($arguments, $this->itemsToBackup);
    }

    protected function getOnUpdateCallback(SymfonyStyle|null $io = null): callable|null
    {
        $lastAdvance = 0;
        $self = $this;

        return static function (string $type, string $buffer) use ($io, $self, &$lastAdvance): void {
            if (Process::OUT === $type) {
                $data = json_decode(trim($buffer), true);

                if (!isset($data['message_type'])) {
                    return;
                }

                if ('status' === $data['message_type'] && isset($data['percent_done'])) {
                    $advance = ((int) ($data['percent_done'] * 100)) - $lastAdvance;
                    $lastAdvance += $advance;
                    $io?->progressAdvance($advance);
                }

                if ('verbose_status' === $data['message_type']) {
                    $io?->writeln(\sprintf('[%s] %s', $data['action'], $data['item']), OutputInterface::VERBOSITY_VERBOSE);
                }

                if ('summary' === $data['message_type']) {
                    $self->summary = $data;
                }
            }
        };
    }

    protected function preRun(SymfonyStyle|null $io): void
    {
        $io?->progressStart(100);
    }

    protected function postRun(SymfonyStyle|null $io): void
    {
        $io?->progressFinish();
    }

    protected function updateResult(string $output, ProcessFailedException|null $exception = null): void
    {
        $this->result = CreateBackupResult::withSummary(
            $output,
            $this->summary,
            $exception,
        );
    }
}
