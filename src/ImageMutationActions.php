<?php

declare(strict_types=1);

namespace SohoPHP\SoFinder\Http;

use SohoPHP\SoFinder\Http\Action\ImageBatchAction;
use SohoPHP\SoFinder\Http\Action\ImageEditAction;

final readonly class ImageMutationActions
{
    public function __construct(public ImageEditAction $edit, public ImageBatchAction $batch)
    {
    }
}
