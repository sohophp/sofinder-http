<?php

declare(strict_types=1);

namespace SohoPHP\SoFinder\Http\Action;

use SohoPHP\SoFinder\Exception\SoFinderException;
use SohoPHP\SoFinder\FileManager;
use SohoPHP\SoFinder\Http\EndpointResult;
use SohoPHP\SoFinder\Http\MutationActionInterface;
use SohoPHP\SoFinder\Http\MutationGuard;
use SohoPHP\SoFinder\Value\OperationResult;
use SohoPHP\SoFinder\Value\RequestContext;

final readonly class BatchAction implements MutationActionInterface
{
    public function __construct(private FileManager $files, private MutationGuard $guard)
    {
    }

    public function endpoint(): string
    {
        return 'sofinder_api_batch';
    }

    public function assertAllowed(RequestContext $context, array $input = []): void
    {
        $this->guard->assertAllowed($context, $input);
    }

    public function execute(RequestContext $context = new RequestContext(), array $input = []): EndpointResult
    {
        $this->assertAllowed($context, $input);
        $paths = $input['paths'] ?? null;
        if (!is_array($paths) || array_filter($paths, static fn (mixed $path): bool => !is_string($path)) !== []) {
            throw new SoFinderException('Batch paths must be an array of strings.', 'invalid_batch_paths', 400);
        }
        $result = $this->files->batch(
            $this->string($input['operation'] ?? ''),
            $this->string($input['resource'] ?? $context->query('resource'), 'Files'),
            array_values($paths),
            $this->string($input['destination'] ?? ''),
            (bool) ($input['overwrite'] ?? false),
            (bool) ($input['autoRename'] ?? true),
        );

        return new EndpointResult(OperationResult::success($result)->jsonSerialize());
    }

    private function string(mixed $value, string $default = ''): string
    {
        return is_scalar($value) || $value instanceof \Stringable ? (string) $value : $default;
    }
}
