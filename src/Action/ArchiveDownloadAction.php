<?php

declare(strict_types=1);

namespace SohoPHP\SoFinder\Http\Action;

use SohoPHP\SoFinder\Archive\ArchiveManager;
use SohoPHP\SoFinder\Exception\SoFinderException;
use SohoPHP\SoFinder\Feature\FeaturePolicy;
use SohoPHP\SoFinder\Http\ContentDisposition;
use SohoPHP\SoFinder\Http\MutationGuard;
use SohoPHP\SoFinder\Http\StreamEndpointResult;
use SohoPHP\SoFinder\Http\StreamingMutationActionInterface;
use SohoPHP\SoFinder\Value\RequestContext;

final class ArchiveDownloadAction implements StreamingMutationActionInterface
{
    public function __construct(
        private readonly ArchiveManager $archives,
        private readonly MutationGuard $guard,
        private readonly FeaturePolicy $features,
    ) {
    }

    public function endpoint(): string
    {
        return 'sofinder_archive_download';
    }

    public function execute(RequestContext $context = new RequestContext(), array $input = []): StreamEndpointResult
    {
        $this->assertAllowed($context, $input);
        $this->features->assertEnabled('archive');
        $paths = $input['paths'] ?? null;
        if (!is_array($paths) || array_filter($paths, static fn (mixed $path): bool => !is_string($path)) !== []) {
            throw new SoFinderException('Archive paths must be an array of strings.', 'invalid_archive_selection', 400);
        }
        $archive = $this->archives->create($this->string($input['resource'] ?? null, 'Files'), array_values($paths));
        $stream = fopen($archive, 'rb');
        if ($stream === false) {
            @unlink($archive);
            throw new SoFinderException('Unable to read the archive.', 'archive_failed', 500);
        }

        return new StreamEndpointResult($stream, headers: [
            'Content-Type' => 'application/zip',
            'Content-Disposition' => ContentDisposition::make('attachment', 'sofinder-download.zip'),
            'Content-Length' => (string) (filesize($archive) ?: 0),
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ], cleanup: static function () use ($archive): void {
            @unlink($archive);
        });
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
