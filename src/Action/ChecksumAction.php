<?php

declare(strict_types=1);

namespace SohoPHP\SoFinder\Http\Action;

use SohoPHP\SoFinder\Exception\SoFinderException;
use SohoPHP\SoFinder\Feature\FeaturePolicy;
use SohoPHP\SoFinder\FileManager;
use SohoPHP\SoFinder\Http\EndpointActionInterface;
use SohoPHP\SoFinder\Http\EndpointResult;
use SohoPHP\SoFinder\Value\RequestContext;

final class ChecksumAction implements EndpointActionInterface
{
    private const MAX_BYTES = 536_870_912;

    public function __construct(private readonly FileManager $files, private readonly FeaturePolicy $features = new FeaturePolicy())
    {
    }

    public function endpoint(): string
    {
        return 'sofinder_api_checksum';
    }

    public function execute(RequestContext $context = new RequestContext(), array $input = []): EndpointResult
    {
        $this->features->assertEnabled('checksum');
        $resource = $this->string($context->query('resource'), 'Files');
        $path = $this->string($context->query('path'));
        $entry = $this->files->entry($resource, $path);
        if ($entry->directory) {
            throw new SoFinderException('Folders do not have a file checksum.', 'invalid_type', 400);
        }
        if ($entry->size > self::MAX_BYTES) {
            throw new SoFinderException('The file is too large for an interactive checksum.', 'checksum_too_large', 413);
        }
        $stream = $this->files->read($resource, $path);
        try {
            $hash = hash_init('sha256');
            hash_update_stream($hash, $stream, self::MAX_BYTES + 1);
            $checksum = hash_final($hash);
        } finally {
            fclose($stream);
        }

        return new EndpointResult(['success' => true, 'data' => ['algorithm' => 'sha256', 'checksum' => $checksum, 'size' => $entry->size]]);
    }

    private function string(mixed $value, string $default = ''): string
    {
        return is_scalar($value) || $value instanceof \Stringable ? (string) $value : $default;
    }
}
