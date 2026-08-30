<?php
declare(strict_types=1);
namespace SohoPHP\SoFinder\Http;
use SohoPHP\SoFinder\Http\Action\ContentAction;
use SohoPHP\SoFinder\Http\Action\DownloadAction;
final class ContentStreamActions
{
    public function __construct(public readonly DownloadAction $download, public readonly ContentAction $content) {}
}
