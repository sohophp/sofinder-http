<?php

declare(strict_types=1);

namespace SohoPHP\SoFinder\Http;

use SohoPHP\SoFinder\Exception\SoFinderException;
use SohoPHP\SoFinder\Image\ImageFormatRegistry;
use SohoPHP\SoFinder\Value\Entry;
use SohoPHP\SoFinder\Value\RequestContext;

final readonly class EntryStreamResponseBuilder
{
    public function __construct(private ImageFormatRegistry $imageFormats = new ImageFormatRegistry()) {}

    /** @param resource $stream
     * @param array<string,string> $extraHeaders
     */
    public function build(RequestContext $context, string $resource, Entry $entry, mixed $stream, ?string $disposition = null, string $cacheControl = 'private, no-cache, must-revalidate', array $extraHeaders = []): StreamEndpointResult
    {
        $etag = '"' . hash('sha256', $resource . "\0" . $entry->path . "\0" . $entry->size . "\0" . $entry->modifiedAt) . '"';
        $headers = $extraHeaders + [
            'ETag' => $etag, 'Last-Modified' => gmdate('D, d M Y H:i:s', $entry->modifiedAt) . ' GMT', 'Cache-Control' => $cacheControl,
            'Accept-Ranges' => 'bytes', 'X-Content-Type-Options' => 'nosniff', 'Content-Security-Policy' => "default-src 'none'; sandbox",
        ];
        $mime = $entry->mimeType ?? 'application/octet-stream';
        $requested = strtolower($disposition ?? $this->string($context->query('disposition'), 'inline'));
        $inline = $requested === 'inline' && $this->imageFormats->isWebEmbeddableMime($mime);
        $headers['Content-Type'] = $mime;
        $headers['Content-Disposition'] = ContentDisposition::make($inline ? 'inline' : 'attachment', $entry->name);
        $headers['Content-Length'] = (string) $entry->size;
        if ($this->notModified($context, $etag, $entry->modifiedAt)) {
            fclose($stream);
            return new StreamEndpointResult(null, 304, $headers);
        }
        $start = 0; $end = max(0, $entry->size - 1); $status = 200;
        $range = $context->header('Range');
        if ($range !== '') { [$start, $end] = $this->parseRange($range, $entry->size); $status = 206; $headers['Content-Range'] = sprintf('bytes %d-%d/%d', $start, $end, $entry->size); }
        $length = $entry->size === 0 ? 0 : $end - $start + 1;
        $headers['Content-Length'] = (string) $length;
        if ($start > 0 && fseek($stream, $start) !== 0) $this->skip($stream, $start);
        if ($length < $entry->size) {
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
    /** @param resource $stream */ private function skip(mixed $stream, int $bytes): void { while ($bytes > 0 && !feof($stream)) { $chunk = fread($stream, min(8192, $bytes)); if ($chunk === false || $chunk === '') break; $bytes -= strlen($chunk); } }
    private function notModified(RequestContext $context, string $etag, int $modifiedAt): bool { $none = $context->header('If-None-Match'); if ($none !== '' && ($none === '*' || in_array($etag, array_map('trim', explode(',', $none)), true))) return true; $since = $context->header('If-Modified-Since'); if ($none === '' && $since !== '') { $time = strtotime($since); return $time !== false && $modifiedAt <= $time; } return false; }
    private function string(mixed $value, string $default = ''): string { return is_scalar($value) || $value instanceof \Stringable ? (string) $value : $default; }
}
