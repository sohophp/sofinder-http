<?php

declare(strict_types=1);

namespace SohoPHP\SoFinder\Http\Action;

use SohoPHP\SoFinder\Feature\FeaturePolicy;
use SohoPHP\SoFinder\FileManager;
use SohoPHP\SoFinder\Http\EndpointResult;
use SohoPHP\SoFinder\Http\MutationActionInterface;
use SohoPHP\SoFinder\Http\MutationGuard;
use SohoPHP\SoFinder\Value\OperationResult;
use SohoPHP\SoFinder\Value\RequestContext;

final class RestoreTrashAction implements MutationActionInterface
{
    public function __construct(
        private readonly FileManager $files,
        private readonly MutationGuard $guard,
        private readonly FeaturePolicy $features = new FeaturePolicy(),
    ) {
    }

    public function endpoint(): string
    {
        return 'sofinder_api_trash_restore';
    }

    public function assertAllowed(RequestContext $context, array $input = []): void
    {
        $this->features->assertEnabled('trash');
        $this->guard->assertAllowed($context, $input);
    }

    public function execute(RequestContext $context = new RequestContext(), array $input = []): EndpointResult
    {
        $this->assertAllowed($context, $input);
        $entry = $this->files->restoreTrash(
            $this->string($input['resource'] ?? $context->query('resource'), 'Files'),
            $this->string($context->attribute('id', $input['id'] ?? '')),
            $this->string($input['conflict'] ?? 'cancel'),
        );

        return new EndpointResult(OperationResult::success(['entry' => $entry])->jsonSerialize());
    }

    private function string(mixed $value, string $default = ''): string
    {
        return is_scalar($value) || $value instanceof \Stringable ? (string) $value : $default;
    }
}
