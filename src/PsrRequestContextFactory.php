<?php

declare(strict_types=1);

namespace SohoPHP\SoFinder\Http;

use Psr\Http\Message\ServerRequestInterface;
use SohoPHP\SoFinder\Value\RequestContext;

final readonly class PsrRequestContextFactory
{
    public function create(ServerRequestInterface $request): RequestContext
    {
        $uri = $request->getUri();
        $schemeAndHost = $uri->getScheme() === '' || $uri->getHost() === ''
            ? ''
            : $uri->getScheme() . '://' . $uri->getAuthority();

        return new RequestContext(
            array_map(
                static fn (array $values): array => array_values(array_filter($values, 'is_string')),
                $request->getHeaders(),
            ),
            $request->getQueryParams(),
            $request->getAttributes(),
            (string) $request->getAttribute('sofinder.base_path', ''),
            $schemeAndHost,
        );
    }
}
