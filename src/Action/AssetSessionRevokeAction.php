<?php

declare(strict_types=1);

namespace SohoPHP\SoFinder\Http\Action;

use SohoPHP\SoFinder\Asset\AssetAccessSessionManager;
use SohoPHP\SoFinder\Http\EndpointResult;
use SohoPHP\SoFinder\Http\MutationActionInterface;
use SohoPHP\SoFinder\Http\MutationGuard;
use SohoPHP\SoFinder\Value\OperationResult;
use SohoPHP\SoFinder\Value\RequestContext;

final class AssetSessionRevokeAction implements MutationActionInterface
{
    public function __construct(private readonly AssetAccessSessionManager $sessions, private readonly MutationGuard $guard)
    {
    }

    public function endpoint(): string
    {
        return 'sofinder_api_asset_session_revoke';
    }

    public function execute(RequestContext $context = new RequestContext(), array $input = []): EndpointResult
    {
        $this->assertAllowed($context, $input);
        $value = $input['id'] ?? $context->attribute('id');
        $id = is_scalar($value) || $value instanceof \Stringable ? (string) $value : '';
        $this->sessions->revoke($id, $context);

        return new EndpointResult(OperationResult::success([])->jsonSerialize());
    }

    public function assertAllowed(RequestContext $context, array $input = []): void
    {
        $this->guard->assertAllowed($context, $input);
    }
}
