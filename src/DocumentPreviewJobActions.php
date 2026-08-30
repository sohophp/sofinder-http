<?php
declare(strict_types=1);
namespace SohoPHP\SoFinder\Http;
use SohoPHP\SoFinder\Http\Action\DocumentPreviewJobCreateAction;
use SohoPHP\SoFinder\Http\Action\DocumentPreviewJobStatusAction;
final class DocumentPreviewJobActions
{
    public function __construct(public readonly DocumentPreviewJobCreateAction $create, public readonly DocumentPreviewJobStatusAction $status) {}
}
