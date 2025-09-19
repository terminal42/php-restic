<?php

declare(strict_types=1);

namespace Terminal42\Restic\Action;

use Symfony\Component\Process\Exception\ProcessFailedException;
use Terminal42\Restic\Action\Result\GetVersionResult;

class GetVersion extends AbstractAction
{
    private GetVersionResult $result;

    public function getResult(): GetVersionResult
    {
        return $this->result;
    }

    protected function doGetArguments(): array
    {
        return ['version'];
    }

    protected function updateResult(string $output, ProcessFailedException|null $exception = null): void
    {
        $this->result = new GetVersionResult($output, $exception);
    }
}
