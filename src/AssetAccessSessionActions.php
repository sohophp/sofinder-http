<?php

declare(strict_types=1);

namespace SohoPHP\SoFinder\Http;

use SohoPHP\SoFinder\Http\Action\AssetSessionContentAction;
use SohoPHP\SoFinder\Http\Action\AssetSessionCreateAction;
use SohoPHP\SoFinder\Http\Action\AssetSessionRevokeAction;

final class AssetAccessSessionActions
{
    public function __construct(
        public readonly AssetSessionCreateAction $create,
        public readonly AssetSessionRevokeAction $revoke,
        public readonly AssetSessionContentAction $content,
    ) {
    }
}
