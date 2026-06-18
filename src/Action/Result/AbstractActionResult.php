<?php

declare(strict_types=1);

namespace Terminal42\Restic\Action\Result;

use Symfony\Component\Process\Exception\ProcessFailedException;

class AbstractActionResult
{
    protected array|null $jsonDecodedOutput = null;

    public function __construct(
        private readonly string $output,
        private readonly ProcessFailedException|null $exception = null,
    ) {
    }

    public function getException(): ProcessFailedException|null
    {
        return $this->exception;
    }

    public function wasSuccessful(): bool
    {
        if (null === $this->exception) {
            return true;
        }

        return $this->isWarningOnlyExitCode($this->exception->getProcess()->getExitCode());
    }

    public function hasWarnings(): bool
    {
        if (null === $this->exception) {
            return false;
        }

        return $this->isWarningOnlyExitCode($this->exception->getProcess()->getExitCode());
    }

    public function getOutput(): string
    {
        return $this->output;
    }

    protected function getJsonDecodedOutput(): array
    {
        if (null === $this->jsonDecodedOutput) {
            $this->jsonDecodedOutput = json_decode(trim($this->getOutput()), true);
        }

        return $this->jsonDecodedOutput;
    }

    protected function getWarningOnlyExitCodes(): array
    {
        return [];
    }

    private function isWarningOnlyExitCode(int|null $exitCode): bool
    {
        return null !== $exitCode && \in_array($exitCode, $this->getWarningOnlyExitCodes(), true);
    }
}
