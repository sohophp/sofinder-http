<?php

declare(strict_types=1);

namespace SohoPHP\SoFinder\Http;

use SohoPHP\SoFinder\Http\Action\ChecksumAction;
use SohoPHP\SoFinder\Http\Action\TextPreviewAction;

final readonly class ContentReadActions
{
    public function __construct(public ChecksumAction $checksum, public TextPreviewAction $textPreview)
    {
    }
}
