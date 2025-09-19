<?php

declare(strict_types=1);

namespace Terminal42\Restic\Action;

use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Terminal42\Restic\Action\Result\AbstractActionResult;
use Terminal42\Restic\Exception\CouldNotRunCommandException;

abstract class AbstractAction
{
    /**
     * @throws CouldNotRunCommandException
     */
    public function run(Process $process, SymfonyStyle|null $io = null): void
    {
        $this->preRun($io);

        try {
            $process->mustRun($this->getOnUpdateCallback($io));
            $this->postRun($io);
            $this->updateResult($process->getOutput());
        } catch (ProcessFailedException $exception) {
            $this->postRun($io);
            $this->updateResult($process->getErrorOutput(), $exception);
        }
    }

    public function getArguments(): array
    {
        return $this->doGetArguments();
    }

    abstract public function getResult(): AbstractActionResult;

    protected function getOnUpdateCallback(SymfonyStyle|null $io = null): callable|null
    {
        return null;
    }

    abstract protected function doGetArguments(): array;

    abstract protected function updateResult(string $output, ProcessFailedException|null $exception = null): void;

    protected function preRun(SymfonyStyle|null $io): void
    {
    }

    protected function postRun(SymfonyStyle|null $io): void
    {
    }
}
