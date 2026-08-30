<?php

declare(strict_types=1);

namespace SohoPHP\SoFinder\Http\Action;

use SohoPHP\SoFinder\Contract\ChunkUploadStoreInterface;
use SohoPHP\SoFinder\Exception\SoFinderException;
use SohoPHP\SoFinder\Http\EndpointResult;
use SohoPHP\SoFinder\Http\MutationActionInterface;
use SohoPHP\SoFinder\Http\MutationGuard;
use SohoPHP\SoFinder\Value\OperationResult;
use SohoPHP\SoFinder\Value\RequestContext;
use SohoPHP\SoFinder\Workspace\WorkspaceProvider;

final class CancelChunkAction implements MutationActionInterface
{
    public function __construct(
        private readonly ChunkUploadStoreInterface $chunks,
        private readonly MutationGuard $guard,
        private readonly ?WorkspaceProvider $workspaces = null,
    ) {
    }

    public function endpoint(): string
    {
        return 'sofinder_api_chunk_cancel';
    }

    public function assertAllowed(RequestContext $context, array $input = []): void
    {
        $this->guard->assertAllowed($context, $input);
    }

    public function execute(RequestContext $context = new RequestContext(), array $input = []): EndpointResult
    {
        $this->assertAllowed($context, $input);
        $id = $this->id($context, $input);
        $state = $this->chunks->status($id);
        if ((string) ($state['workspace'] ?? '') !== ($this->workspaces?->current($context)->id ?? '')) {
            throw new SoFinderException('The upload session does not belong to the current workspace.', 'upload_session_not_found', 404);
        }
        $this->chunks->discard($id);

        return new EndpointResult(OperationResult::success()->jsonSerialize());
    }

    /** @param array<string, mixed> $input */
    private function id(RequestContext $context, array $input): string
    {
        $id = $input['id'] ?? $context->attribute('id');
        return is_scalar($id) || $id instanceof \Stringable ? (string) $id : '';
    }
}
