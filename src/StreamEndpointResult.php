<?php

declare(strict_types=1);

namespace SohoPHP\SoFinder\Http;

/** Framework-neutral streaming response; ownership of the stream transfers to the bridge. */
final class StreamEndpointResult
{
    /**
     * @param resource|null $stream
     * @param array<string,string> $headers
     * @param (\Closure():void)|null $cleanup
     */
    public function __construct(
        public readonly mixed $stream,
        public readonly int $status = 200,
        public readonly array $headers = [],
        public readonly ?\Closure $cleanup = null,
    ) {
        if ($stream !== null && !is_resource($stream)) {
            throw new \InvalidArgumentException('A stream endpoint result requires a stream resource or null.');
        }
        if ($status < 100 || $status > 599) {
            throw new \InvalidArgumentException('An endpoint result status must be between 100 and 599.');
        }
    }
}
