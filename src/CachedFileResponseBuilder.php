<?php

declare(strict_types=1);

namespace SohoPHP\SoFinder\Http;

use SohoPHP\SoFinder\Exception\SoFinderException;
use SohoPHP\SoFinder\Value\RequestContext;

final class CachedFileResponseBuilder
{
    /** @param array<string,string> $headers */
    public function build(RequestContext $context, string $path, string $mimeType, int $maxAge, array $headers = []): StreamEndpointResult
    {
        $size = filesize($path); $modifiedAt = filemtime($path);
        if (!is_int($size) || !is_int($modifiedAt)) throw new SoFinderException('The generated file is unavailable.', 'generated_file_unavailable', 500);
        $etag = '"' . hash('sha256', $path . "\0" . $size . "\0" . $modifiedAt) . '"';
        $headers += ['Content-Type' => $mimeType, 'Content-Length' => (string) $size, 'ETag' => $etag, 'Last-Modified' => gmdate('D, d M Y H:i:s', $modifiedAt) . ' GMT', 'Cache-Control' => 'private, max-age=' . $maxAge, 'X-Content-Type-Options' => 'nosniff'];
        if ($context->header('If-None-Match') === $etag) return new StreamEndpointResult(null, 304, $headers);
        $stream = fopen($path, 'rb');
        if ($stream === false) throw new SoFinderException('The generated file cannot be read.', 'generated_file_unavailable', 500);
        return new StreamEndpointResult($stream, headers: $headers);
    }
}
