<?php

declare(strict_types=1);

namespace SohoPHP\SoFinder\Http;

use SohoPHP\SoFinder\Http\Action\AssetDeleteCheckAction;
use SohoPHP\SoFinder\Http\Action\AssetUsageListAction;
use SohoPHP\SoFinder\Http\Action\AssetUsagePutAction;
use SohoPHP\SoFinder\Http\Action\AssetUsageRemoveAction;

final readonly class AssetUsageActions
{
    public function __construct(
        public AssetUsageListAction $list,
        public AssetUsagePutAction $put,
        public AssetUsageRemoveAction $remove,
        public AssetDeleteCheckAction $deleteCheck,
    ) {
    }
}
