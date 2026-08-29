<?php

declare(strict_types=1);

namespace SohoPHP\SoFinder\Http\Action;

use SohoPHP\SoFinder\Http\AssetUsageService;
use SohoPHP\SoFinder\Http\EndpointResult;
use SohoPHP\SoFinder\Http\MutationActionInterface;
use SohoPHP\SoFinder\Http\MutationGuard;
use SohoPHP\SoFinder\Value\OperationResult;
use SohoPHP\SoFinder\Value\RequestContext;

final readonly class AssetUsagePutAction implements MutationActionInterface
{
    public function __construct(private AssetUsageService $service, private MutationGuard $guard) {}
    public function endpoint(): string { return 'sofinder_api_asset_usage_put'; }
    public function assertAllowed(RequestContext $context, array $input = []): void { $this->guard->assertAllowed($context, $input); }
    public function execute(RequestContext $context = new RequestContext(), array $input = []): EndpointResult
    {
        $this->assertAllowed($context, $input);
        return new EndpointResult(OperationResult::success($this->service->put($context, $this->attribute($context, $input, 'id'), $this->attribute($context, $input, 'referenceId'), $input))->jsonSerialize());
    }
    /** @param array<string,mixed> $input */
    private function attribute(RequestContext $context, array $input, string $name): string
    {
        $value = $input[$name] ?? $context->attribute($name);
        return is_scalar($value) || $value instanceof \Stringable ? (string) $value : '';
    }
}
