<?php

declare(strict_types=1);

namespace Terminal42\Restic\Action\Result;

class GetVersionResult extends AbstractActionResult
{
    public function getVersion(): string
    {
        preg_match('/restic ([^ ]*)/', $this->getOutput(), $matches);

        return $matches[1] ?? '';
    }
}
