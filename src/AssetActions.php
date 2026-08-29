<?php
declare(strict_types=1);
namespace SohoPHP\SoFinder\Http;
use SohoPHP\SoFinder\Http\Action\AssetGetAction;
use SohoPHP\SoFinder\Http\Action\AssetResolveAction;
use SohoPHP\SoFinder\Http\Action\AssetUpdateAction;
final readonly class AssetActions
{
    public function __construct(public AssetResolveAction $resolve, public AssetGetAction $get, public AssetUpdateAction $update) {}
}
