<?php
declare(strict_types=1);
namespace SohoPHP\SoFinder\Http\Action;
use SohoPHP\SoFinder\Http\AssetService;
use SohoPHP\SoFinder\Http\EndpointResult;
use SohoPHP\SoFinder\Http\MutationActionInterface;
use SohoPHP\SoFinder\Http\MutationGuard;
use SohoPHP\SoFinder\Value\OperationResult;
use SohoPHP\SoFinder\Value\RequestContext;
final readonly class AssetUpdateAction implements MutationActionInterface
{
    public function __construct(private AssetService $service, private MutationGuard $guard) {}
    public function endpoint(): string { return 'sofinder_api_asset_update'; }
    public function assertAllowed(RequestContext $context, array $input = []): void { $this->guard->assertAllowed($context, $input); }
    public function execute(RequestContext $context = new RequestContext(), array $input = []): EndpointResult { $this->assertAllowed($context, $input); return new EndpointResult(OperationResult::success($this->service->update($context, $this->id($context, $input), $input))->jsonSerialize()); }
    /** @param array<string,mixed> $input */ private function id(RequestContext $context, array $input): string { $id = $input['id'] ?? $context->attribute('id'); return is_scalar($id) || $id instanceof \Stringable ? (string) $id : ''; }
}
