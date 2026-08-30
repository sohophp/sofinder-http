<?php

declare(strict_types=1);

namespace SohoPHP\SoFinder\Http;

use SohoPHP\SoFinder\Http\Action\AssetDeleteCheckAction;
use SohoPHP\SoFinder\Http\Action\AssetUsageListAction;
use SohoPHP\SoFinder\Http\Action\AssetUsagePutAction;
use SohoPHP\SoFinder\Http\Action\AssetUsageRemoveAction;

final class AssetUsageActions
{
    public function __construct(
        public readonly AssetUsageListAction $list,
        public readonly AssetUsagePutAction $put,
        public readonly AssetUsageRemoveAction $remove,
        public readonly AssetDeleteCheckAction $deleteCheck,
    ) {
    }
}
