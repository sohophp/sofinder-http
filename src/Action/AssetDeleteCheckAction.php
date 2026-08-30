<?php

declare(strict_types=1);

namespace SohoPHP\SoFinder\Http\Action;

use SohoPHP\SoFinder\Http\AssetUsageService;
use SohoPHP\SoFinder\Http\EndpointResult;
use SohoPHP\SoFinder\Http\MutationActionInterface;
use SohoPHP\SoFinder\Http\MutationGuard;
use SohoPHP\SoFinder\Value\OperationResult;
use SohoPHP\SoFinder\Value\RequestContext;

final class AssetDeleteCheckAction implements MutationActionInterface
{
    public function __construct(private readonly AssetUsageService $service, private readonly MutationGuard $guard) {}
    public function endpoint(): string { return 'sofinder_api_asset_delete_check'; }
    public function assertAllowed(RequestContext $context, array $input = []): void { $this->guard->assertAllowed($context, $input); }
    public function execute(RequestContext $context = new RequestContext(), array $input = []): EndpointResult
    {
        $this->assertAllowed($context, $input);
        return new EndpointResult(OperationResult::success($this->service->deleteCheck($context, $input))->jsonSerialize());
    }
}
