<?php

declare(strict_types=1);

namespace SohoPHP\SoFinder\Http;

use SohoPHP\SoFinder\Http\Action\MetadataGetAction;
use SohoPHP\SoFinder\Http\Action\MetadataUpdateAction;

final readonly class MetadataActions
{
    public function __construct(public MetadataGetAction $get, public MetadataUpdateAction $update)
    {
    }
}
