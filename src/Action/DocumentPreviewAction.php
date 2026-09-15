<?php

declare(strict_types=1);

namespace SohoPHP\SoFinder\Http\Action;

use SohoPHP\SoFinder\Exception\SoFinderException;
use SohoPHP\SoFinder\Feature\FeaturePolicy;
use SohoPHP\SoFinder\Http\ContentDisposition;
use SohoPHP\SoFinder\Http\CachedFileResponseBuilder;
use SohoPHP\SoFinder\Http\EndpointActionInterface;
use SohoPHP\SoFinder\Http\StreamEndpointResult;
use SohoPHP\SoFinder\Preview\DocumentPreviewJobManager;
use SohoPHP\SoFinder\Preview\DocumentPreviewManager;
use SohoPHP\SoFinder\Value\RequestContext;

final class DocumentPreviewAction implements EndpointActionInterface
{
    public function __construct(private readonly DocumentPreviewManager $previews, private readonly FeaturePolicy $features = new FeaturePolicy(), private readonly ?DocumentPreviewJobManager $jobs = null, private readonly CachedFileResponseBuilder $responses = new CachedFileResponseBuilder()) {}
    public function endpoint(): string { return 'sofinder_document_preview'; }
    public function execute(RequestContext $context = new RequestContext(), array $input = []): StreamEndpointResult
    {
        $this->features->assertEnabled('document_preview');
        $resource = $this->string($context->query('resource'), 'Files'); $path = $this->string($context->query('path'));
        $description = $this->previews->describe($resource, $path);
        if ($description['source'] === 'office' && !$description['cached'] && $this->jobs?->asynchronous()) throw new SoFinderException('The Office preview is still being prepared.', 'document_preview_pending', 202);
        $preview = $this->previews->preview($resource, $path);
        return $this->responses->build($context, $preview['file'], 'application/pdf', 0, ['Content-Disposition' => ContentDisposition::make('inline', $preview['name']), 'Cache-Control' => 'private, no-store', 'Content-Security-Policy' => "frame-ancestors 'self'"]);
    }
    private function string(mixed $value, string $default = ''): string { return is_scalar($value) || $value instanceof \Stringable ? (string) $value : $default; }
}
