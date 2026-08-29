<?php

declare(strict_types=1);

namespace SohoPHP\SoFinder\Http;

/** Framework-neutral result returned by shared HTTP application actions. */
final readonly class EndpointResult
{
    /**
     * @param array<string, mixed> $payload
     * @param array<string, string> $headers
     */
    public function __construct(
        public array $payload,
        public int $status = 200,
        public array $headers = [],
    ) {
        if ($status < 100 || $status > 599) {
            throw new \InvalidArgumentException('An endpoint result status must be between 100 and 599.');
        }
    }
}
