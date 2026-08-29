<?php

declare(strict_types=1);

namespace SohoPHP\SoFinder\Http\Action;

use SohoPHP\SoFinder\Http\EndpointActionInterface;
use SohoPHP\SoFinder\Http\EndpointResult;
use SohoPHP\SoFinder\Http\MetadataPayload;
use SohoPHP\SoFinder\Value\OperationResult;
use SohoPHP\SoFinder\Value\RequestContext;

final readonly class MetadataGetAction implements EndpointActionInterface
{
    public function __construct(private MetadataPayload $metadata)
    {
    }

    public function endpoint(): string
    {
        return 'sofinder_metadata_get';
    }

    public function execute(RequestContext $context = new RequestContext(), array $input = []): EndpointResult
    {
        $this->metadata->assertAvailable();
        $resource = $this->string($context->query('resource'), 'Files');

        return new EndpointResult(OperationResult::success($this->metadata->forResource($resource))->jsonSerialize());
    }

    private function string(mixed $value, string $default = ''): string
    {
        return is_scalar($value) || $value instanceof \Stringable ? (string) $value : $default;
    }
}
