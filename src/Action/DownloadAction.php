<?php

declare(strict_types=1);

namespace SohoPHP\SoFinder\Http\Action;

use SohoPHP\SoFinder\Exception\SoFinderException;
use SohoPHP\SoFinder\FileManager;
use SohoPHP\SoFinder\Http\EntryStreamResponseBuilder;
use SohoPHP\SoFinder\Http\EndpointActionInterface;
use SohoPHP\SoFinder\Http\StreamEndpointResult;
use SohoPHP\SoFinder\Value\RequestContext;

final readonly class DownloadAction implements EndpointActionInterface
{
    public function __construct(
        private FileManager $files,
        private EntryStreamResponseBuilder $responses = new EntryStreamResponseBuilder(),
    ) {}
    public function endpoint(): string { return 'sofinder_api_download'; }
    public function execute(RequestContext $context = new RequestContext(), array $input = []): StreamEndpointResult
    {
        $resource = $this->string($context->query('resource'), 'Files');
        $path = $this->string($context->query('path'));
        $entry = $this->files->entry($resource, $path);
        if ($entry->directory) {
            throw new SoFinderException('Folders cannot be downloaded directly.', 'invalid_type', 400);
        }

        return $this->responses->build(
            $context,
            $resource,
            $entry,
            $this->files->read($resource, $path),
            'attachment',
        );
    }
    private function string(mixed $value, string $default = ''): string { return is_scalar($value) || $value instanceof \Stringable ? (string) $value : $default; }
}
