<?php
declare(strict_types=1);
namespace SohoPHP\SoFinder\Http\Action;
use SohoPHP\SoFinder\Http\AssetService;
use SohoPHP\SoFinder\Http\EndpointActionInterface;
use SohoPHP\SoFinder\Http\EndpointResult;
use SohoPHP\SoFinder\Value\OperationResult;
use SohoPHP\SoFinder\Value\RequestContext;
final readonly class AssetGetAction implements EndpointActionInterface
{
    public function __construct(private AssetService $service) {}
    public function endpoint(): string { return 'sofinder_api_asset_get'; }
    public function execute(RequestContext $context = new RequestContext(), array $input = []): EndpointResult { return new EndpointResult(OperationResult::success($this->service->get($context, $this->id($context, $input)))->jsonSerialize()); }
    /** @param array<string,mixed> $input */ private function id(RequestContext $context, array $input): string { $id = $input['id'] ?? $context->attribute('id'); return is_scalar($id) || $id instanceof \Stringable ? (string) $id : ''; }
}
