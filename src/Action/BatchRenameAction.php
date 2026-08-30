<?php

declare(strict_types=1);

namespace SohoPHP\SoFinder\Http\Action;

use SohoPHP\SoFinder\Exception\SoFinderException;
use SohoPHP\SoFinder\Feature\FeaturePolicy;
use SohoPHP\SoFinder\FileManager;
use SohoPHP\SoFinder\Http\EndpointResult;
use SohoPHP\SoFinder\Http\MutationActionInterface;
use SohoPHP\SoFinder\Http\MutationGuard;
use SohoPHP\SoFinder\Value\OperationResult;
use SohoPHP\SoFinder\Value\RequestContext;

final class BatchRenameAction implements MutationActionInterface
{
    public function __construct(
        private readonly FileManager $files,
        private readonly MutationGuard $guard,
        private readonly FeaturePolicy $features = new FeaturePolicy(),
    ) {
    }

    public function endpoint(): string
    {
        return 'sofinder_api_batch_rename';
    }

    public function assertAllowed(RequestContext $context, array $input = []): void
    {
        $this->features->assertEnabled('batch_rename');
        $this->guard->assertAllowed($context, $input);
    }

    public function execute(RequestContext $context = new RequestContext(), array $input = []): EndpointResult
    {
        $this->assertAllowed($context, $input);
        $renames = $input['renames'] ?? null;
        if (!is_array($renames)) {
            throw new SoFinderException('Batch renames must be an array.', 'invalid_batch_paths', 400);
        }
        $normalized = [];
        foreach ($renames as $rename) {
            if (!is_array($rename) || !is_string($rename['path'] ?? null) || !is_string($rename['name'] ?? null)) {
                throw new SoFinderException('Each batch rename requires string path and name fields.', 'invalid_batch_paths', 400);
            }
            $normalized[] = ['path' => $rename['path'], 'name' => $rename['name']];
        }
        $result = $this->files->batchRename(
            $this->string($input['resource'] ?? $context->query('resource'), 'Files'),
            $normalized,
        );

        return new EndpointResult(OperationResult::success($result)->jsonSerialize());
    }

    private function string(mixed $value, string $default = ''): string
    {
        return is_scalar($value) || $value instanceof \Stringable ? (string) $value : $default;
    }
}
