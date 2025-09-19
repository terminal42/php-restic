<?php

declare(strict_types=1);

namespace Terminal42\Restic\Action;

use Symfony\Component\Process\Exception\ProcessFailedException;
use Terminal42\Restic\Action\Result\InitRepositoryResult;

class InitRepository extends AbstractAction
{
    private InitRepositoryResult $result;

    public function getResult(): InitRepositoryResult
    {
        return $this->result;
    }

    protected function doGetArguments(): array
    {
        return [
            'init',
        ];
    }

    protected function updateResult(string $output, ProcessFailedException|null $exception = null): void
    {
        $this->result = new InitRepositoryResult($output, $exception);
    }
}
