<?php

declare(strict_types=1);

namespace SohoPHP\SoFinder\Http;

use SohoPHP\SoFinder\Http\Action\CancelChunkAction;
use SohoPHP\SoFinder\Http\Action\ChunkStatusAction;
use SohoPHP\SoFinder\Http\Action\ChunkUploadAction;

final class ChunkUploadActions
{
    public function __construct(public readonly ChunkStatusAction $status, public readonly CancelChunkAction $cancel, public readonly ?ChunkUploadAction $upload = null)
    {
    }
}
