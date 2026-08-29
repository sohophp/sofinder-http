<?php

declare(strict_types=1);

namespace SohoPHP\SoFinder\Http\Action;

use SohoPHP\SoFinder\Http\AssetUsageService;
use SohoPHP\SoFinder\Http\EndpointActionInterface;
use SohoPHP\SoFinder\Http\EndpointResult;
use SohoPHP\SoFinder\Value\OperationResult;
use SohoPHP\SoFinder\Value\RequestContext;

final readonly class AssetUsageListAction implements EndpointActionInterface
{
    public function __construct(private AssetUsageService $service) {}
    public function endpoint(): string { return 'sofinder_api_asset_usage_list'; }
    public function execute(RequestContext $context = new RequestContext(), array $input = []): EndpointResult
    {
        return new EndpointResult(OperationResult::success($this->service->list($context, $this->attribute($context, $input, 'id')))->jsonSerialize());
    }
    /** @param array<string,mixed> $input */
    private function attribute(RequestContext $context, array $input, string $name): string
    {
        $value = $input[$name] ?? $context->attribute($name);
        return is_scalar($value) || $value instanceof \Stringable ? (string) $value : '';
    }
}
