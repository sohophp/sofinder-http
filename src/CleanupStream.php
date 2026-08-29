<?php

declare(strict_types=1);

namespace SohoPHP\SoFinder\Http;

use Psr\Http\Message\StreamInterface;

/** Ensures endpoint-owned temporary resources are released when a PSR body closes. */
final class CleanupStream implements StreamInterface
{
    private ?\Closure $cleanup;

    public function __construct(private StreamInterface $stream, \Closure $cleanup)
    {
        $this->cleanup = $cleanup;
    }

    public function __destruct()
    {
        $this->close();
    }

    public function __toString(): string { return (string) $this->stream; }
    public function close(): void { $this->stream->close(); $this->runCleanup(); }
    public function detach() { $resource = $this->stream->detach(); $this->runCleanup(); return $resource; }
    public function getSize(): ?int { return $this->stream->getSize(); }
    public function tell(): int { return $this->stream->tell(); }
    public function eof(): bool { return $this->stream->eof(); }
    public function isSeekable(): bool { return $this->stream->isSeekable(); }
    public function seek(int $offset, int $whence = SEEK_SET): void { $this->stream->seek($offset, $whence); }
    public function rewind(): void { $this->stream->rewind(); }
    public function isWritable(): bool { return $this->stream->isWritable(); }
    public function write(string $string): int { return $this->stream->write($string); }
    public function isReadable(): bool { return $this->stream->isReadable(); }
    public function read(int $length): string { return $this->stream->read($length); }
    public function getContents(): string { return $this->stream->getContents(); }
    public function getMetadata(?string $key = null) { return $this->stream->getMetadata($key); }

    private function runCleanup(): void
    {
        $cleanup = $this->cleanup;
        $this->cleanup = null;
        if ($cleanup !== null) {
            $cleanup();
        }
    }
}
