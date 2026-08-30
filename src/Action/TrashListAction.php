<?php

declare(strict_types=1);

namespace SohoPHP\SoFinder\Http\Action;

use SohoPHP\SoFinder\Feature\FeaturePolicy;
use SohoPHP\SoFinder\FileManager;
use SohoPHP\SoFinder\Http\EndpointActionInterface;
use SohoPHP\SoFinder\Http\EndpointResult;
use SohoPHP\SoFinder\Value\OperationResult;
use SohoPHP\SoFinder\Value\RequestContext;

final class TrashListAction implements EndpointActionInterface
{
    public function __construct(
        private readonly FileManager $files,
        private readonly FeaturePolicy $features = new FeaturePolicy(),
    ) {
    }

    public function endpoint(): string
    {
        return 'sofinder_api_trash';
    }

    public function execute(RequestContext $context = new RequestContext(), array $input = []): EndpointResult
    {
        $this->features->assertEnabled('trash');
        $result = $this->files->trash(
            $this->string($context->query('resource'), 'Files'),
            $this->integer($context->query('offset')),
            $this->integer($context->query('limit'), 50),
            $this->string($context->query('search')),
        );

        return new EndpointResult(OperationResult::success($result)->jsonSerialize());
    }

    private function string(mixed $value, string $default = ''): string
    {
        return is_scalar($value) || $value instanceof \Stringable ? (string) $value : $default;
    }

    private function integer(mixed $value, int $default = 0): int
    {
        return filter_var($value, FILTER_VALIDATE_INT) !== false ? (int) $value : $default;
    }
}
