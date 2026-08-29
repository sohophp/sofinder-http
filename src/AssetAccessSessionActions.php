<?php

declare(strict_types=1);

namespace SohoPHP\SoFinder\Http;

use SohoPHP\SoFinder\Http\Action\AssetSessionContentAction;
use SohoPHP\SoFinder\Http\Action\AssetSessionCreateAction;
use SohoPHP\SoFinder\Http\Action\AssetSessionRevokeAction;

final readonly class AssetAccessSessionActions
{
    public function __construct(
        public AssetSessionCreateAction $create,
        public AssetSessionRevokeAction $revoke,
        public AssetSessionContentAction $content,
    ) {
    }
}
