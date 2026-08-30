<?php

declare(strict_types=1);

namespace SohoPHP\SoFinder\Http\Action;

use SohoPHP\SoFinder\Exception\SoFinderException;
use SohoPHP\SoFinder\Http\EndpointResult;
use SohoPHP\SoFinder\Http\MetadataPayload;
use SohoPHP\SoFinder\Http\MutationActionInterface;
use SohoPHP\SoFinder\Http\MutationGuard;
use SohoPHP\SoFinder\Metadata\MetadataManager;
use SohoPHP\SoFinder\Value\OperationResult;
use SohoPHP\SoFinder\Value\RequestContext;

final class MetadataUpdateAction implements MutationActionInterface
{
    public function __construct(
        private readonly MetadataManager $metadata,
        private readonly MetadataPayload $payload,
        private readonly MutationGuard $guard,
    ) {
    }

    public function endpoint(): string
    {
        return 'sofinder_metadata_update';
    }

    public function assertAllowed(RequestContext $context, array $input = []): void
    {
        $this->guard->assertAllowed($context, $input);
    }

    public function execute(RequestContext $context = new RequestContext(), array $input = []): EndpointResult
    {
        $this->assertAllowed($context, $input);
        $resource = $this->string($input['resource'] ?? $context->query('resource'), 'Files');
        $path = $this->string($input['path'] ?? '');
        $action = $this->string($input['action'] ?? '');
        $this->payload->assertAction($action);
        if ($action === 'favorite') {
            $this->metadata->favorite($resource, $path, (bool) ($input['favorite'] ?? false));
        } elseif ($action === 'quick_access') {
            $this->metadata->quickAccess($resource, $path, (bool) ($input['pinned'] ?? false));
        } elseif ($action === 'tags') {
            $this->metadata->tags($resource, $path, $this->tags($input['tags'] ?? null));
        } elseif ($action === 'touch') {
            $this->metadata->touch($resource, $path);
        } else {
            $this->metadata->forget($resource, $path);
        }

        return new EndpointResult(OperationResult::success($this->payload->forResource($resource))->jsonSerialize());
    }

    /** @return list<string> */
    private function tags(mixed $tags): array
    {
        if (!is_array($tags) || array_filter($tags, static fn (mixed $tag): bool => !is_string($tag)) !== []) {
            throw new SoFinderException('Tags must be an array of strings.', 'invalid_tags', 422);
        }

        return array_values($tags);
    }

    private function string(mixed $value, string $default = ''): string
    {
        return is_scalar($value) || $value instanceof \Stringable ? (string) $value : $default;
    }
}
