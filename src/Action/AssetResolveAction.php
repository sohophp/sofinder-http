<?php
declare(strict_types=1);
namespace SohoPHP\SoFinder\Http\Action;
use SohoPHP\SoFinder\Http\AssetService;
use SohoPHP\SoFinder\Http\EndpointActionInterface;
use SohoPHP\SoFinder\Http\EndpointResult;
use SohoPHP\SoFinder\Value\OperationResult;
use SohoPHP\SoFinder\Value\RequestContext;
final class AssetResolveAction implements EndpointActionInterface
{
    public function __construct(private readonly AssetService $service) {}
    public function endpoint(): string { return 'sofinder_api_asset_resolve'; }
    public function execute(RequestContext $context = new RequestContext(), array $input = []): EndpointResult { return new EndpointResult(OperationResult::success($this->service->resolve($context))->jsonSerialize()); }
}
