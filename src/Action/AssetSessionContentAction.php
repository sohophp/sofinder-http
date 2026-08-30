<?php

declare(strict_types=1);

namespace SohoPHP\SoFinder\Http\Action;

use SohoPHP\SoFinder\Asset\AssetAccessSessionManager;
use SohoPHP\SoFinder\Http\EndpointActionInterface;
use SohoPHP\SoFinder\Http\EntryStreamResponseBuilder;
use SohoPHP\SoFinder\Http\StreamEndpointResult;
use SohoPHP\SoFinder\Value\RequestContext;

final class AssetSessionContentAction implements EndpointActionInterface
{
    public function __construct(
        private readonly AssetAccessSessionManager $sessions,
        private readonly EntryStreamResponseBuilder $responses,
    ) {
    }

    public function endpoint(): string
    {
        return 'sofinder_asset_session_content';
    }

    public function execute(RequestContext $context = new RequestContext(), array $input = []): StreamEndpointResult
    {
        $token = $this->string($input['token'] ?? $context->attribute('token'));
        $assetId = $this->string($input['assetId'] ?? $context->attribute('assetId'));
        $opened = $this->sessions->open($token, $assetId);

        return $this->responses->build(
            $context,
            $opened['resource'],
            $opened['entry'],
            $opened['stream'],
            'inline',
            'private, max-age=' . max(0, $opened['expiresAt'] - time()),
            ['Referrer-Policy' => 'no-referrer'],
        );
    }

    private function string(mixed $value): string
    {
        return is_scalar($value) || $value instanceof \Stringable ? (string) $value : '';
    }
}
