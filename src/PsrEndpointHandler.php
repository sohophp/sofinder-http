<?php

declare(strict_types=1);

namespace SohoPHP\SoFinder\Http;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Http\Message\StreamFactoryInterface;
use SohoPHP\SoFinder\Exception\SoFinderException;
use SohoPHP\SoFinder\Framework\ScopedRequestContextProvider;

/** Converts a shared application action into the PSR-7 endpoint contract. */
final class PsrEndpointHandler implements EndpointHandlerInterface
{
    public function __construct(
        private readonly EndpointActionInterface $action,
        private readonly ResponseFactoryInterface $responses,
        private readonly StreamFactoryInterface $streams,
        private readonly PsrRequestContextFactory $contexts = new PsrRequestContextFactory(),
        private readonly ?ScopedRequestContextProvider $scope = null,
    ) {
        EndpointCatalog::get($action->endpoint());
    }

    public function endpoint(): string
    {
        return $this->action->endpoint();
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $context = $this->contexts->create($request);
        $this->scope?->push($context);
        try {
            if ($this->action instanceof GuardedActionInterface) {
                $parsed = $request->getParsedBody();
                $this->action->assertAllowed($context, is_array($parsed) ? $parsed : []);
            }
            $result = $this->action->execute($context, $this->input($request));
            if ($result instanceof StreamEndpointResult) {
                $response = $this->responses->createResponse($result->status);
                if ($result->stream !== null) {
                    $body = $this->streams->createStreamFromResource($result->stream);
                    $response = $response->withBody($result->cleanup === null ? $body : new CleanupStream($body, $result->cleanup));
                } elseif ($result->cleanup !== null) {
                    ($result->cleanup)();
                }
                foreach ($result->headers as $name => $value) {
                    $response = $response->withHeader($name, $value);
                }

                return $response;
            }
            $body = $this->streams->createStream(json_encode($result->payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
            $response = $this->responses->createResponse($result->status)
                ->withHeader('Content-Type', 'application/json; charset=utf-8')
                ->withBody($body);
            foreach ($result->headers as $name => $value) {
                $response = $response->withHeader($name, $value);
            }

            return $response;
        } finally {
            $this->scope?->pop();
        }
    }

    /** @return array<string, mixed> */
    private function input(ServerRequestInterface $request): array
    {
        $parsed = $request->getParsedBody();
        $contentType = strtolower($request->getHeaderLine('Content-Type'));
        $body = (string) $request->getBody();
        if (is_array($parsed) && ($parsed !== [] || $body === '' || !str_starts_with($contentType, 'application/json'))) {
            return array_replace($parsed, $this->uploadedFiles($request->getUploadedFiles()));
        }
        if ($body === '' || !str_starts_with($contentType, 'application/json')) {
            return $this->uploadedFiles($request->getUploadedFiles());
        }
        try {
            $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new SoFinderException('The JSON request body is invalid.', 'invalid_json', 400);
        }
        if (!is_array($decoded)) {
            throw new SoFinderException('The JSON request body must be an object.', 'invalid_json', 400);
        }

        return array_replace($decoded, $this->uploadedFiles($request->getUploadedFiles()));
    }

    /**
     * @param array<string,UploadedFileInterface|array<array-key,UploadedFileInterface>> $files
     * @return array<string,mixed>
     */
    private function uploadedFiles(array $files): array
    {
        $mapped = [];
        foreach ($files as $name => $file) {
            if ($file instanceof UploadedFileInterface) {
                $mapped[$name] = UploadedFileInput::fromPsr($file);
                continue;
            }
            if (is_array($file)) {
                $mapped[$name] = $this->uploadedFiles($file);
            }
        }

        return $mapped;
    }
}
