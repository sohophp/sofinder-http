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
        $headers += ['Content-Type' => $mimeType, 'Content-Length' => (string) $size, 'ETag' => $etag, 'Last-Modified' => gmdate('D, d M Y H:i:s', $modifiedAt) . ' GMT', 'Cache-Control' => 'private, max-age=' . $maxAge, 'Accept-Ranges' => 'bytes', 'X-Content-Type-Options' => 'nosniff'];
        if ($context->header('If-None-Match') === $etag) return new StreamEndpointResult(null, 304, $headers);
        $stream = fopen($path, 'rb');
        if ($stream === false) throw new SoFinderException('The generated file cannot be read.', 'generated_file_unavailable', 500);
        $start = 0; $end = max(0, $size - 1); $status = 200;
        $range = $context->header('Range');
        if ($range !== '') { [$start, $end] = $this->parseRange($range, $size); $status = 206; $headers['Content-Range'] = sprintf('bytes %d-%d/%d', $start, $end, $size); }
        $length = $size === 0 ? 0 : $end - $start + 1;
        $headers['Content-Length'] = (string) $length;
        if ($start > 0 && fseek($stream, $start) !== 0) { fclose($stream); throw new SoFinderException('The generated file cannot be read.', 'generated_file_unavailable', 500); }
        if ($length < $size) {
            $bounded = fopen('php://temp', 'w+b');
            if ($bounded === false) { fclose($stream); throw new SoFinderException('Unable to prepare the requested content range.', 'content_stream_failed', 500); }
            stream_copy_to_stream($stream, $bounded, $length); fclose($stream); rewind($bounded); $stream = $bounded;
        }
        return new StreamEndpointResult($stream, $status, $headers);
    }

    /** @return array{int,int} */
    private function parseRange(string $range, int $size): array
    {
        if ($size < 1 || preg_match('/^bytes=(\d*)-(\d*)$/D', trim($range), $matches) !== 1 || ($matches[1] === '' && $matches[2] === '')) throw new SoFinderException('The requested byte range is not satisfiable.', 'invalid_range', 416);
        if ($matches[1] === '') { $suffix = (int) $matches[2]; if ($suffix < 1) throw new SoFinderException('The requested byte range is not satisfiable.', 'invalid_range', 416); $start = max(0, $size - $suffix); $end = $size - 1; }
        else { $start = (int) $matches[1]; $end = $matches[2] === '' ? $size - 1 : min((int) $matches[2], $size - 1); }
        if ($start >= $size || $end < $start) throw new SoFinderException('The requested byte range is not satisfiable.', 'invalid_range', 416);
        return [$start, $end];
    }
}
