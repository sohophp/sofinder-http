<?php

declare(strict_types=1);

namespace SohoPHP\SoFinder\Http\Action;

use SohoPHP\SoFinder\Exception\SoFinderException;
use SohoPHP\SoFinder\Feature\FeaturePolicy;
use SohoPHP\SoFinder\FileManager;
use SohoPHP\SoFinder\Http\EndpointActionInterface;
use SohoPHP\SoFinder\Http\EndpointResult;
use SohoPHP\SoFinder\Value\RequestContext;

final readonly class TextPreviewAction implements EndpointActionInterface
{
    private const MAX_BYTES = 262_144;

    public function __construct(private FileManager $files, private FeaturePolicy $features = new FeaturePolicy())
    {
    }

    public function endpoint(): string
    {
        return 'sofinder_api_text_preview';
    }

    public function execute(RequestContext $context = new RequestContext(), array $input = []): EndpointResult
    {
        $this->features->assertEnabled('text_preview');
        $resource = $this->string($context->query('resource'), 'Files');
        $path = $this->string($context->query('path'));
        $entry = $this->files->entry($resource, $path);
        $mime = strtolower($entry->mimeType ?? '');
        if ($entry->directory || !$this->isTextMime($mime)) {
            throw new SoFinderException('This file type does not support a text preview.', 'preview_unsupported', 415);
        }
        $stream = $this->files->read($resource, $path);
        try {
            $content = stream_get_contents($stream, self::MAX_BYTES + 1);
            if ($content === false) {
                throw new SoFinderException('Unable to read the text preview.', 'preview_failed', 500);
            }
        } finally {
            fclose($stream);
        }
        $truncated = strlen($content) > self::MAX_BYTES;
        if ($truncated) {
            $content = substr($content, 0, self::MAX_BYTES);
        }
        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            $content = substr($content, 3);
        }
        if (!mb_check_encoding($content, 'UTF-8')) {
            throw new SoFinderException('Only UTF-8 text files can be previewed.', 'preview_encoding_unsupported', 415);
        }

        return new EndpointResult(['success' => true, 'data' => ['content' => $content, 'truncated' => $truncated, 'mimeType' => $mime, 'size' => $entry->size]]);
    }

    private function isTextMime(string $mime): bool
    {
        return str_starts_with($mime, 'text/') || in_array($mime, [
            'application/json', 'application/ld+json', 'application/xml', 'application/x-yaml', 'application/yaml',
        ], true) || str_ends_with($mime, '+json') || str_ends_with($mime, '+xml');
    }

    private function string(mixed $value, string $default = ''): string
    {
        return is_scalar($value) || $value instanceof \Stringable ? (string) $value : $default;
    }
}
