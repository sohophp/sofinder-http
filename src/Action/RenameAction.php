<?php

declare(strict_types=1);

namespace SohoPHP\SoFinder\Http\Action;

use SohoPHP\SoFinder\FileManager;
use SohoPHP\SoFinder\Http\EndpointResult;
use SohoPHP\SoFinder\Http\MutationActionInterface;
use SohoPHP\SoFinder\Http\MutationGuard;
use SohoPHP\SoFinder\Value\OperationResult;
use SohoPHP\SoFinder\Value\RequestContext;

final class RenameAction implements MutationActionInterface
{
    public function __construct(private readonly FileManager $files, private readonly MutationGuard $guard)
    {
    }

    public function endpoint(): string
    {
        return 'sofinder_api_rename';
    }

    public function execute(RequestContext $context = new RequestContext(), array $input = []): EndpointResult
    {
        $this->assertAllowed($context, $input);
        $entry = $this->files->rename(
            $this->string($input['resource'] ?? $context->query('resource'), 'Files'),
            $this->string($input['path'] ?? ''),
            $this->string($input['name'] ?? ''),
            (bool) ($input['overwrite'] ?? false),
        );

        return new EndpointResult(OperationResult::success(['entry' => $entry])->jsonSerialize());
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
