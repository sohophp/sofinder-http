<?php

declare(strict_types=1);

namespace SohoPHP\SoFinder\Http;

use SohoPHP\SoFinder\Asset\AssetOperationPublisher;
use SohoPHP\SoFinder\Value\RequestContext;

final class ImageMutationService
{
    public function __construct(private readonly ?AssetOperationPublisher $events = null)
    {
    }

    /**
     * @template T
     * @param callable():T $operation
     * @param array<string,mixed> $attributes
     * @return T
     */
    public function process(
        string $resource,
        string $path,
        callable $operation,
        array $attributes = [],
        ?RequestContext $context = null,
    ): mixed {
        $id = $this->events?->operationId();
        if ($id !== null) {
            $this->events?->dispatch($id, 'image.process', 'before', $resource, $path, attributes: $attributes, context: $context);
        }
        try {
            $result = $operation();
        } catch (\Throwable $error) {
            if ($id !== null) {
                $errorCode = $this->events->errorCode($error);
                $this->events->dispatch($id, 'image.process', 'failed', $resource, $path, attributes: ['errorCode' => $errorCode] + $attributes, context: $context);
            }
            throw $error;
        }
        if ($id !== null) {
            $this->events->dispatch($id, 'image.process', 'after', $resource, $path, attributes: $attributes, context: $context);
        }

        return $result;
    }
}
