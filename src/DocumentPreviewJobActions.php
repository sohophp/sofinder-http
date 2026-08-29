<?php
declare(strict_types=1);
namespace SohoPHP\SoFinder\Http;
use SohoPHP\SoFinder\Http\Action\DocumentPreviewJobCreateAction;
use SohoPHP\SoFinder\Http\Action\DocumentPreviewJobStatusAction;
final readonly class DocumentPreviewJobActions
{
    public function __construct(public DocumentPreviewJobCreateAction $create, public DocumentPreviewJobStatusAction $status) {}
}
