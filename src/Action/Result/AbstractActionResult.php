<?php

declare(strict_types=1);

namespace Terminal42\Restic\Action\Result;

use Symfony\Component\Process\Exception\ProcessFailedException;

class AbstractActionResult
{
    public function __construct(
        private readonly string $output,
        private readonly ProcessFailedException|null $exception = null,
    ) {
    }

    public function wasSuccessful(): bool
    {
        return null === $this->exception;
    }

    public function getException(): ProcessFailedException|null
    {
        return $this->exception;
    }

    public function getOutput(): string
    {
        return $this->output;
    }
}
