<?php

declare(strict_types=1);

namespace SohoPHP\SoFinder\Http;

use SohoPHP\SoFinder\Value\RequestContext;

interface GuardedActionInterface extends EndpointActionInterface
{
    /** @param array<string,mixed> $input */
    public function assertAllowed(RequestContext $context, array $input = []): void;
}
