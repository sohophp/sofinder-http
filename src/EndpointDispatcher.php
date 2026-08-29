<?php

declare(strict_types=1);

namespace SohoPHP\SoFinder\Http;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\RequestHandlerInterface;
use SohoPHP\SoFinder\Exception\SoFinderException;

final class EndpointDispatcher implements RequestHandlerInterface
{
    /** @var array<string, EndpointHandlerInterface> */
    private array $handlers = [];

    /** @param iterable<EndpointHandlerInterface> $handlers */
    public function __construct(
        private readonly ResponseFactoryInterface $responses,
        private readonly StreamFactoryInterface $streams,
        iterable $handlers,
    ) {
        foreach ($handlers as $handler) {
            $name = $handler->endpoint();
            EndpointCatalog::get($name);
            if (isset($this->handlers[$name])) {
                throw new \InvalidArgumentException(sprintf('Duplicate SoFinder endpoint handler "%s".', $name));
            }
            $this->handlers[$name] = $handler;
        }
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $name = $request->getAttribute('sofinder.endpoint');
        if (!is_string($name) || $name === '') {
            return $this->error('endpoint_not_resolved', 'The SoFinder endpoint was not resolved.', 404);
        }

        return $this->dispatch($name, $request);
    }

    public function dispatch(string $name, ServerRequestInterface $request): ResponseInterface
    {
        $handler = $this->handlers[$name] ?? null;
        if ($handler === null) {
            return $this->error('endpoint_not_implemented', 'The SoFinder endpoint is not implemented by this host.', 501);
        }

        try {
            return $this->secure($handler->handle($request));
        } catch (SoFinderException $exception) {
            $headers = [];
            if ($exception->httpStatus === 429) {
                $headers['Retry-After'] = '2';
            }
            if ($exception->httpStatus === 202 && $exception->errorCode === 'document_preview_pending') {
                $headers['Retry-After'] = '1';
            }

            return $this->error($exception->errorCode, $exception->getMessage(), $exception->httpStatus, $headers);
        }
    }

    /** @param array<string, string> $headers */
    private function error(string $code, string $message, int $status, array $headers = []): ResponseInterface
    {
        $body = $this->streams->createStream(json_encode([
            'success' => false,
            'error' => ['code' => $code, 'message' => $message],
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

        $response = $this->responses->createResponse($status)
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withBody($body);
        foreach ($headers as $name => $value) {
            $response = $response->withHeader($name, $value);
        }

        return $this->secure($response);
    }

    private function secure(ResponseInterface $response): ResponseInterface
    {
        if (!$response->hasHeader('Cache-Control') && str_starts_with(strtolower($response->getHeaderLine('Content-Type')), 'application/json')) {
            $response = $response->withHeader('Cache-Control', 'no-store');
        }

        return $response
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('X-Frame-Options', 'SAMEORIGIN')
            ->withHeader('Referrer-Policy', 'same-origin');
    }
}
