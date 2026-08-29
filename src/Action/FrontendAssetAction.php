<?php

declare(strict_types=1);

namespace SohoPHP\SoFinder\Http\Action;

use SohoPHP\SoFinder\Http\CachedFileResponseBuilder;
use SohoPHP\SoFinder\Http\EndpointActionInterface;
use SohoPHP\SoFinder\Http\StreamEndpointResult;
use SohoPHP\SoFinder\Value\RequestContext;

final readonly class FrontendAssetAction implements EndpointActionInterface
{
    public function __construct(private string $packageDirectory, private CachedFileResponseBuilder $responses)
    {
    }

    public function endpoint(): string
    {
        return 'sofinder_asset';
    }

    public function execute(RequestContext $context = new RequestContext(), array $input = []): StreamEndpointResult
    {
        $value = $input['file'] ?? $context->attribute('file');
        $file = is_scalar($value) || $value instanceof \Stringable ? (string) $value : '';
        $allowed = [];
        foreach ($this->manifestAssets() as $asset) {
            $allowed[$asset] = str_ends_with($asset, '.css') ? 'text/css; charset=UTF-8' : 'text/javascript; charset=UTF-8';
        }
        if (!isset($allowed[$file])) {
            return $this->text('Not found', 404);
        }
        $path = $this->packageDirectory . '/dist/' . $file;
        if (!is_file($path)) {
            return $this->text('SoFinder assets have not been built.', 503);
        }

        return $this->responses->build($context, $path, $allowed[$file], 31_536_000, [
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }

    /** @return list<string> */
    private function manifestAssets(): array
    {
        $manifest = $this->packageDirectory . '/dist/manifest.json';
        if (!is_file($manifest) || !is_readable($manifest)) {
            return [];
        }
        try {
            $decoded = json_decode((string) file_get_contents($manifest), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }
        if (!is_array($decoded)) {
            return [];
        }
        $assets = [];
        foreach ($decoded as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $candidates = [$entry['file'] ?? null, ...(is_array($entry['css'] ?? null) ? $entry['css'] : [])];
            foreach ($candidates as $candidate) {
                if (!is_string($candidate) || basename($candidate) !== $candidate || preg_match('/^[A-Za-z0-9._-]+\.(?:js|css)$/D', $candidate) !== 1) {
                    continue;
                }
                $assets[$candidate] = true;
            }
        }

        return array_keys($assets);
    }

    private function text(string $content, int $status): StreamEndpointResult
    {
        $stream = fopen('php://temp', 'w+b');
        if ($stream === false) {
            throw new \RuntimeException('Unable to create the asset response stream.');
        }
        fwrite($stream, $content);
        rewind($stream);

        return new StreamEndpointResult($stream, $status, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Length' => (string) strlen($content),
            'Cache-Control' => 'no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
