<?php

declare(strict_types=1);

namespace SohoPHP\SoFinder\Http\Action;

use SohoPHP\SoFinder\FileManager;
use SohoPHP\SoFinder\Http\EndpointResult;
use SohoPHP\SoFinder\Http\MutationActionInterface;
use SohoPHP\SoFinder\Http\MutationGuard;
use SohoPHP\SoFinder\Value\OperationResult;
use SohoPHP\SoFinder\Value\RequestContext;

final readonly class DeleteAction implements MutationActionInterface
{
    public function __construct(private FileManager $files, private MutationGuard $guard)
    {
    }

    public function endpoint(): string
    {
        return 'sofinder_api_delete';
    }

    public function execute(RequestContext $context = new RequestContext(), array $input = []): EndpointResult
    {
        $this->assertAllowed($context, $input);
        $trashed = $this->files->delete(
            $this->string($input['resource'] ?? $context->query('resource'), 'Files'),
            $this->string($input['path'] ?? ''),
        );

        return new EndpointResult(OperationResult::success(['trash' => $trashed])->jsonSerialize());
    }

    public function assertAllowed(RequestContext $context, array $input = []): void
    {
        $this->guard->assertAllowed($context, $input);
    }

    private function string(mixed $value, string $default = ''): string
    {
        return is_scalar($value) || $value instanceof \Stringable ? (string) $value : $default;
    }
}
