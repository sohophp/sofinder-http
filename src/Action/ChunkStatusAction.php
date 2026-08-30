<?php

declare(strict_types=1);

namespace SohoPHP\SoFinder\Http\Action;

use SohoPHP\SoFinder\Contract\ChunkUploadStoreInterface;
use SohoPHP\SoFinder\Exception\SoFinderException;
use SohoPHP\SoFinder\FileManager;
use SohoPHP\SoFinder\Http\EndpointActionInterface;
use SohoPHP\SoFinder\Http\EndpointResult;
use SohoPHP\SoFinder\Value\OperationResult;
use SohoPHP\SoFinder\Value\RequestContext;
use SohoPHP\SoFinder\Workspace\WorkspaceProvider;

final class ChunkStatusAction implements EndpointActionInterface
{
    public function __construct(
        private readonly FileManager $files,
        private readonly ChunkUploadStoreInterface $chunks,
        private readonly ?WorkspaceProvider $workspaces = null,
    ) {
    }

    public function endpoint(): string
    {
        return 'sofinder_api_chunk_status';
    }

    public function execute(RequestContext $context = new RequestContext(), array $input = []): EndpointResult
    {
        $state = $this->chunks->status($this->id($context, $input));
        $this->assertSessionWorkspace($state, $context);
        $this->files->uploadLimit($state['resource'], $state['path'], $state['name']);

        return new EndpointResult(OperationResult::success($state)->jsonSerialize());
    }

    /** @param array<string, mixed> $input */
    private function id(RequestContext $context, array $input): string
    {
        $id = $input['id'] ?? $context->attribute('id');
        return is_scalar($id) || $id instanceof \Stringable ? (string) $id : '';
    }

    /** @param array<string, mixed> $state */
    private function assertSessionWorkspace(array $state, RequestContext $context): void
    {
        if ((string) ($state['workspace'] ?? '') !== ($this->workspaces?->current($context)->id ?? '')) {
            throw new SoFinderException('The upload session does not belong to the current workspace.', 'upload_session_not_found', 404);
        }
    }
}
