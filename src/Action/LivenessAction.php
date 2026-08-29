<?php

declare(strict_types=1);

namespace SohoPHP\SoFinder\Http\Action;

use SohoPHP\SoFinder\Http\EndpointActionInterface;
use SohoPHP\SoFinder\Http\EndpointResult;
use SohoPHP\SoFinder\Value\RequestContext;

final readonly class LivenessAction implements EndpointActionInterface
{
    public function endpoint(): string
    {
        return 'sofinder_liveness';
    }

    public function execute(RequestContext $context = new RequestContext(), array $input = []): EndpointResult
    {
        return new EndpointResult(['success' => true, 'data' => ['status' => 'ready']]);
    }
}
