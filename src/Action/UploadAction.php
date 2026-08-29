<?php

declare(strict_types=1);

namespace SohoPHP\SoFinder\Http\Action;

use SohoPHP\SoFinder\Asset\AssetReferenceBuilder;
use SohoPHP\SoFinder\Exception\SoFinderException;
use SohoPHP\SoFinder\FileManager;
use SohoPHP\SoFinder\Http\EndpointResult;
use SohoPHP\SoFinder\Http\MutationActionInterface;
use SohoPHP\SoFinder\Http\MutationGuard;
use SohoPHP\SoFinder\Http\UploadedFileInput;
use SohoPHP\SoFinder\Upload\UploadNamePolicy;
use SohoPHP\SoFinder\Value\OperationResult;
use SohoPHP\SoFinder\Value\RequestContext;

final readonly class UploadAction implements MutationActionInterface
{
    public function __construct(
        private FileManager $files,
        private MutationGuard $guard,
        private UploadNamePolicy $names,
        private ?AssetReferenceBuilder $assets = null,
    ) {
    }

    public function endpoint(): string
    {
        return 'sofinder_api_upload';
    }

    public function execute(RequestContext $context = new RequestContext(), array $input = []): EndpointResult
    {
        $this->assertAllowed($context, $input);
        $uploaded = $input['upload'] ?? null;
        if (!$uploaded instanceof UploadedFileInput || $uploaded->error !== UPLOAD_ERR_OK) {
            throw new SoFinderException('No valid uploaded file was received.', 'invalid_upload', 400);
        }
        $resource = $this->string($input['resource'] ?? $context->query('resource'), 'Files');
        $stream = $uploaded->open();
        try {
            $entry = $this->files->upload(
                $resource,
                $this->string($input['path'] ?? ''),
                $this->names->normalize($uploaded->clientName),
                $uploaded->size,
                $stream,
                $this->boolean($input['overwrite'] ?? false),
                $this->boolean($input['autoRename'] ?? false),
            );
        } finally {
            fclose($stream);
        }

        return new EndpointResult(OperationResult::success([
            'entry' => $entry,
            'asset' => $this->assets?->create($resource, $entry, context: $context),
        ])->jsonSerialize(), 201);
    }

    public function assertAllowed(RequestContext $context, array $input = []): void
    {
        $this->guard->assertAllowed($context, $input);
    }

    private function string(mixed $value, string $default = ''): string
    {
        return is_scalar($value) || $value instanceof \Stringable ? (string) $value : $default;
    }

    private function boolean(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOL);
    }
}
