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

    protected function getJsonDecodedOutput(): array
    {
        if (null === $this->jsonDecodedOutput) {
            $this->jsonDecodedOutput = json_decode(trim($this->getOutput()), true);
        }

        return $this->jsonDecodedOutput;
    }
}
