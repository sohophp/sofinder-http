<?php
declare(strict_types=1);
namespace SohoPHP\SoFinder\Http;
use SohoPHP\SoFinder\Http\Action\AssetGetAction;
use SohoPHP\SoFinder\Http\Action\AssetResolveAction;
use SohoPHP\SoFinder\Http\Action\AssetUpdateAction;
final class AssetActions
{
    public function __construct(public readonly AssetResolveAction $resolve, public readonly AssetGetAction $get, public readonly AssetUpdateAction $update) {}
}
