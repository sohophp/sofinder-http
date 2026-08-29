<?php

declare(strict_types=1);

namespace SohoPHP\SoFinder\Http;

use SohoPHP\SoFinder\Value\RequestContext;

interface StreamingMutationActionInterface extends GuardedActionInterface
{
    /** @param array<string,mixed> $input */
    public function execute(RequestContext $context = new RequestContext(), array $input = []): StreamEndpointResult;
}
