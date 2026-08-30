<?php

declare(strict_types=1);

namespace SohoPHP\SoFinder\Http\Action;

use SohoPHP\SoFinder\FileManager;
use SohoPHP\SoFinder\Http\EndpointResult;
use SohoPHP\SoFinder\Http\MutationActionInterface;
use SohoPHP\SoFinder\Http\MutationGuard;
use SohoPHP\SoFinder\Value\OperationResult;
use SohoPHP\SoFinder\Value\RequestContext;

final class TransferAction implements MutationActionInterface
{
    public function __construct(
        private readonly FileManager $files,
        private readonly MutationGuard $guard,
        private readonly string $operation,
    ) {
        if (!in_array($operation, ['copy', 'move'], true)) {
            throw new \InvalidArgumentException('A transfer action operation must be copy or move.');
        }
    }

    public function endpoint(): string
    {
        return $this->operation === 'copy' ? 'sofinder_api_copy' : 'sofinder_api_move';
    }

    public function execute(RequestContext $context = new RequestContext(), array $input = []): EndpointResult
    {
        $this->assertAllowed($context, $input);
        $entry = $this->files->transfer(
            $this->operation,
            $this->string($input['resource'] ?? $context->query('resource'), 'Files'),
            $this->string($input['path'] ?? ''),
            $this->string($input['destination'] ?? ''),
            (bool) ($input['overwrite'] ?? false),
            (bool) ($input['autoRename'] ?? false),
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
