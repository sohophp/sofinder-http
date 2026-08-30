<?php

declare(strict_types=1);

namespace SohoPHP\SoFinder\Http\Action;

use SohoPHP\SoFinder\Http\EndpointActionInterface;
use SohoPHP\SoFinder\Http\EndpointResult;
use SohoPHP\SoFinder\Value\CapabilityCatalog;
use SohoPHP\SoFinder\Value\OperationResult;
use SohoPHP\SoFinder\Value\RequestContext;

final class CapabilityAction implements EndpointActionInterface
{
    public function __construct(private readonly CapabilityCatalog $catalog)
    {
    }

    public function endpoint(): string
    {
        return 'sofinder_api_capabilities';
    }

    public function execute(RequestContext $context = new RequestContext(), array $input = []): EndpointResult
    {
        return new EndpointResult(OperationResult::success($this->catalog->jsonSerialize())->jsonSerialize());
    }
}
