<?php

declare(strict_types=1);

namespace SohoPHP\SoFinder\Http;

use SohoPHP\SoFinder\Http\Action\ImageBatchAction;
use SohoPHP\SoFinder\Http\Action\ImageEditAction;

final class ImageMutationActions
{
    public function __construct(public readonly ImageEditAction $edit, public readonly ImageBatchAction $batch)
    {
    }
}
