<?php
declare(strict_types=1);
namespace SohoPHP\SoFinder\Http;
use SohoPHP\SoFinder\Http\Action\ContentAction;
use SohoPHP\SoFinder\Http\Action\DownloadAction;
final readonly class ContentStreamActions
{
    public function __construct(public DownloadAction $download, public ContentAction $content) {}
}
