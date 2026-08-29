<?php

declare(strict_types=1);

namespace SohoPHP\SoFinder\Http\Action;

use SohoPHP\SoFinder\Asset\AssetAccessSessionManager;
use SohoPHP\SoFinder\Contract\EndpointUrlGeneratorInterface;
use SohoPHP\SoFinder\Http\EndpointResult;
use SohoPHP\SoFinder\Http\MutationActionInterface;
use SohoPHP\SoFinder\Http\MutationGuard;
use SohoPHP\SoFinder\Value\OperationResult;
use SohoPHP\SoFinder\Value\RequestContext;

final readonly class AssetSessionCreateAction implements MutationActionInterface
{
    public function __construct(
        private AssetAccessSessionManager $sessions,
        private MutationGuard $guard,
        private EndpointUrlGeneratorInterface $urls,
    ) {
    }

    public function endpoint(): string
    {
        return 'sofinder_api_asset_session_create';
    }

    public function execute(RequestContext $context = new RequestContext(), array $input = []): EndpointResult
    {
        $this->assertAllowed($context, $input);
        $ids = array_values(array_filter(is_array($input['assetIds'] ?? null) ? $input['assetIds'] : [], 'is_string'));
        $created = $this->sessions->create($ids, isset($input['ttl']) ? (int) $input['ttl'] : null, $context);
        $token = $created['token'];
        $assets = array_map(fn (array $asset): array => $asset + [
            'url' => $this->urls->generate('sofinder_asset_session_content', ['token' => $token, 'assetId' => $asset['assetId']], true),
        ], $created['assets']);

        return new EndpointResult(OperationResult::success([
            'id' => $created['id'],
            'expiresAt' => $created['expiresAt'],
            'assets' => $assets,
        ])->jsonSerialize(), 201);
    }

    public function assertAllowed(RequestContext $context, array $input = []): void
    {
        $this->guard->assertAllowed($context, $input);
    }
}
