<?php

declare(strict_types=1);

namespace SohoPHP\SoFinder\Http;

use SohoPHP\SoFinder\Http\Action\BatchAction;
use SohoPHP\SoFinder\Http\Action\BatchRenameAction;

final readonly class BatchMutationActions
{
    public function __construct(public BatchAction $batch, public BatchRenameAction $rename)
    {
    }
}
