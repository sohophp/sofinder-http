<?php

declare(strict_types=1);

namespace SohoPHP\SoFinder\Http\Action;

use SohoPHP\SoFinder\Http\EndpointActionInterface;
use SohoPHP\SoFinder\Http\EndpointResult;
use SohoPHP\SoFinder\Image\ImageManager;
use SohoPHP\SoFinder\Value\OperationResult;
use SohoPHP\SoFinder\Value\RequestContext;

final readonly class ImageInfoAction implements EndpointActionInterface
{
    public function __construct(private ImageManager $images)
    {
    }

    public function endpoint(): string
    {
        return 'sofinder_image_info';
    }

    public function execute(RequestContext $context = new RequestContext(), array $input = []): EndpointResult
    {
        $resource = $this->string($context->query('resource'), 'Images');
        $path = $this->string($context->query('path'));

        return new EndpointResult(OperationResult::success($this->images->info($resource, $path))->jsonSerialize());
    }

    private function string(mixed $value, string $default = ''): string
    {
        return is_scalar($value) || $value instanceof \Stringable ? (string) $value : $default;
    }
}
