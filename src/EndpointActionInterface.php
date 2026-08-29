<?php

declare(strict_types=1);

namespace SohoPHP\SoFinder\Http;

use SohoPHP\SoFinder\Value\RequestContext;

/** Shared application action usable by every framework bridge. */
interface EndpointActionInterface
{
    public function endpoint(): string;

    /** @param array<string, mixed> $input */
    public function execute(RequestContext $context = new RequestContext(), array $input = []): EndpointResult|StreamEndpointResult;
}
