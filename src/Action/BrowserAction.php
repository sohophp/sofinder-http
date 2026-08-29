<?php

declare(strict_types=1);

namespace SohoPHP\SoFinder\Http\Action;

use SohoPHP\SoFinder\Http\BrowserPage;
use SohoPHP\SoFinder\Http\EndpointActionInterface;
use SohoPHP\SoFinder\Http\StreamEndpointResult;
use SohoPHP\SoFinder\Value\RequestContext;

/** Framework-neutral HTML shell used by PSR-15 and framework-free hosts. */
final readonly class BrowserAction implements EndpointActionInterface
{
    public function __construct(private BrowserPage $page)
    {
    }

    public function endpoint(): string
    {
        return 'sofinder_browser';
    }

    public function execute(RequestContext $context = new RequestContext(), array $input = []): StreamEndpointResult
    {
        $html = $this->page->render($context);
        $stream = fopen('php://temp', 'w+b');
        if ($stream === false) {
            throw new \RuntimeException('Unable to create the browser response stream.');
        }
        fwrite($stream, $html);
        rewind($stream);

        return new StreamEndpointResult($stream, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Length' => (string) strlen($html),
            'Cache-Control' => 'no-store, private',
        ]);
    }
}
