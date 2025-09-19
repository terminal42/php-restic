<?php

declare(strict_types=1);

namespace Terminal42\Restic\Action\Result;

class InitRepositoryResult extends AbstractActionResult
{
    public function wasSuccessful(): bool
    {
        // Failed, might fail because it already exists in which case we want to handle this as success
        if (!parent::wasSuccessful()) {
            return str_contains($this->getOutput(), 'config file already exists')
                || str_contains($this->getOutput(), 'config already initialized');
        }

        return true;
    }
}
