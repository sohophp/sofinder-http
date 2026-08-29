<?php

declare(strict_types=1);

namespace SohoPHP\SoFinder\Http\Action;

use SohoPHP\SoFinder\Asset\AssetReferenceBuilder;
use SohoPHP\SoFinder\Contract\ChunkUploadStoreInterface;
use SohoPHP\SoFinder\Exception\SoFinderException;
use SohoPHP\SoFinder\FileManager;
use SohoPHP\SoFinder\Http\EndpointResult;
use SohoPHP\SoFinder\Http\MutationActionInterface;
use SohoPHP\SoFinder\Http\MutationGuard;
use SohoPHP\SoFinder\Http\UploadedFileInput;
use SohoPHP\SoFinder\Maintenance\MaintenanceCoordinator;
use SohoPHP\SoFinder\Maintenance\MaintenanceTask;
use SohoPHP\SoFinder\Upload\UploadNamePolicy;
use SohoPHP\SoFinder\Value\OperationResult;
use SohoPHP\SoFinder\Value\RequestContext;
use SohoPHP\SoFinder\Workspace\WorkspaceProvider;

final readonly class ChunkUploadAction implements MutationActionInterface
{
    public function __construct(
        private FileManager $files,
        private ChunkUploadStoreInterface $chunks,
        private MutationGuard $guard,
        private UploadNamePolicy $names,
        private ?MaintenanceCoordinator $maintenance = null,
        private ?AssetReferenceBuilder $assets = null,
        private ?WorkspaceProvider $workspaces = null,
    ) {
    }

    public function endpoint(): string
    {
        return 'sofinder_api_chunk_upload';
    }

    public function execute(RequestContext $context = new RequestContext(), array $input = []): EndpointResult
    {
        $this->assertAllowed($context, $input);
        $uploaded = $input['chunk'] ?? null;
        if (!$uploaded instanceof UploadedFileInput || $uploaded->error !== UPLOAD_ERR_OK) {
            throw new SoFinderException('No valid upload chunk was received.', 'invalid_upload_chunk', 400);
        }
        $id = $this->string($input['uploadId'] ?? null);
        $resource = $this->string($input['resource'] ?? null, 'Files');
        $path = $this->string($input['path'] ?? null);
        $name = $this->names->normalize($this->string($input['name'] ?? null));
        $limit = $this->files->uploadLimit($resource, $path, $name);
        try {
            $stream = $uploaded->open();
        } catch (SoFinderException $error) {
            throw new SoFinderException('Unable to read the upload chunk.', 'invalid_upload_chunk', 400, $error);
        }
        try {
            $state = $this->chunks->accept(
                $id,
                $this->integer($input['index'] ?? null, -1),
                $this->integer($input['total'] ?? null),
                $stream,
                $limit,
                [
                    'resource' => $resource,
                    'path' => $path,
                    'name' => $name,
                    'overwrite' => $this->boolean($input['overwrite'] ?? false),
                    'autoRename' => $this->boolean($input['autoRename'] ?? false),
                    'workspace' => $this->workspaceId($context),
                ],
            );
        } finally {
            fclose($stream);
        }
        if (!$state['complete']) {
            return new EndpointResult(OperationResult::success(['complete' => false])->jsonSerialize());
        }
        if (!isset($state['path'], $state['size'])) {
            throw new SoFinderException('The completed upload session is missing its assembled file.', 'chunk_assembly_failed', 500);
        }
        $session = $this->chunks->status($id);
        $this->assertSessionWorkspace($session, $context);
        $assembled = @fopen((string) $state['path'], 'rb');
        if ($assembled === false) {
            throw new SoFinderException('Unable to read the assembled upload.', 'chunk_assembly_failed', 500);
        }
        try {
            $entry = $this->files->upload(
                $session['resource'],
                $session['path'],
                $session['name'],
                (int) $state['size'],
                $assembled,
                $session['overwrite'],
                $session['autoRename'],
            );
        } finally {
            fclose($assembled);
            $this->chunks->discard($id);
        }
        $this->maintenance?->trigger(MaintenanceTask::Uploads);

        return new EndpointResult(OperationResult::success([
            'complete' => true,
            'entry' => $entry,
            'asset' => $this->assets?->create($session['resource'], $entry, context: $context),
        ])->jsonSerialize(), 201);
    }

    public function assertAllowed(RequestContext $context, array $input = []): void
    {
        $this->guard->assertAllowed($context, $input);
    }

    /** @param array<string,mixed> $session */
    private function assertSessionWorkspace(array $session, RequestContext $context): void
    {
        if ((string) ($session['workspace'] ?? '') !== $this->workspaceId($context)) {
            throw new SoFinderException('The upload session does not belong to the current workspace.', 'upload_session_not_found', 404);
        }
    }

    private function workspaceId(RequestContext $context): string
    {
        return $this->workspaces?->current($context)->id ?? '';
    }

    private function string(mixed $value, string $default = ''): string
    {
        return is_scalar($value) || $value instanceof \Stringable ? (string) $value : $default;
    }

    private function integer(mixed $value, int $default = 0): int
    {
        return filter_var($value, FILTER_VALIDATE_INT) !== false ? (int) $value : $default;
    }

    private function boolean(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOL);
    }
}
