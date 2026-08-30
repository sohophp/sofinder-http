<?php

declare(strict_types=1);

namespace SohoPHP\SoFinder\Http;

use SohoPHP\SoFinder\Http\Action\MetadataGetAction;
use SohoPHP\SoFinder\Http\Action\MetadataUpdateAction;

final class MetadataActions
{
    public function __construct(public readonly MetadataGetAction $get, public readonly MetadataUpdateAction $update)
    {
    }
}
