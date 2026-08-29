<?php

declare(strict_types=1);

namespace SohoPHP\SoFinder\Http;

use SohoPHP\SoFinder\Http\Action\CancelChunkAction;
use SohoPHP\SoFinder\Http\Action\ChunkStatusAction;
use SohoPHP\SoFinder\Http\Action\ChunkUploadAction;

final readonly class ChunkUploadActions
{
    public function __construct(public ChunkStatusAction $status, public CancelChunkAction $cancel, public ?ChunkUploadAction $upload = null)
    {
    }
}
