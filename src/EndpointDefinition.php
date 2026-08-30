<?php

declare(strict_types=1);

namespace SohoPHP\SoFinder\Http;

final class EndpointDefinition
{
    /**
     * @param non-empty-list<string> $methods
     * @param array<string, string> $requirements
     */
    public function __construct(
        public readonly string $name,
        public readonly string $path,
        public readonly array $methods,
        public readonly array $requirements = [],
        public readonly bool $public = false,
    ) {
        if ($name === '' || !str_starts_with($path, '/') || $methods === []) {
            throw new \InvalidArgumentException('Endpoint name, absolute path and at least one method are required.');
        }
        foreach ($methods as $method) {
            if (preg_match('/^[A-Z]+$/D', $method) !== 1) {
                throw new \InvalidArgumentException('Endpoint methods must be uppercase HTTP tokens.');
            }
        }
    }
}
