<?php

declare(strict_types=1);

namespace SohoPHP\SoFinder\Http\Action;

use SohoPHP\SoFinder\Feature\FeaturePolicy;
use SohoPHP\SoFinder\FileManager;
use SohoPHP\SoFinder\Http\EndpointActionInterface;
use SohoPHP\SoFinder\Http\EndpointResult;
use SohoPHP\SoFinder\Metadata\MetadataManager;
use SohoPHP\SoFinder\Value\OperationResult;
use SohoPHP\SoFinder\Value\RequestContext;

final class EntriesAction implements EndpointActionInterface
{
    private const MIN_PAGE_SIZE = 10;
    private const MAX_PAGE_SIZE = 500;

    public function __construct(
        private readonly FileManager $files,
        private readonly ?MetadataManager $metadata = null,
        private readonly FeaturePolicy $features = new FeaturePolicy(),
    ) {
    }

    public function endpoint(): string
    {
        return 'sofinder_api_entries';
    }

    public function execute(RequestContext $context = new RequestContext(), array $input = []): EndpointResult
    {
        $resource = $this->string($context->query('resource'), 'Files');
        $search = $this->string($context->query('search'));
        $searchMode = strtolower($this->string($context->query('searchMode'), 'name'));
        $onlyPaths = null;
        if ($searchMode === 'tags') {
            $this->features->assertEnabled('tags');
            if (trim($search) !== '') {
                $onlyPaths = $this->taggedPaths($resource, $search);
                $search = '';
            }
        }
        $data = $this->files->list(
            $resource,
            $this->string($context->query('path')),
            $search,
            $this->string($context->query('sort'), 'name'),
            $this->string($context->query('direction'), 'asc'),
            $this->integer($context->query('offset')),
            max(self::MIN_PAGE_SIZE, min(self::MAX_PAGE_SIZE, $this->integer($context->query('limit'), 100))),
            $onlyPaths,
            $context->query('cursor') === null ? null : $this->string($context->query('cursor')),
        );

        return new EndpointResult(OperationResult::success($data)->jsonSerialize());
    }

    /** @return list<string> */
    private function taggedPaths(string $resource, string $search): array
    {
        if ($this->metadata === null) {
            return [];
        }
        $terms = array_values(array_unique(array_filter(array_map(
            static fn (string $term): string => mb_strtolower(trim($term)),
            preg_split('/[,，]+/u', $search) ?: [],
        ))));
        if ($terms === []) {
            return [];
        }
        $paths = [];
        foreach ($this->metadata->get($resource)['tags'] as $path => $tags) {
            $normalized = array_map(static fn (string $tag): string => mb_strtolower($tag), $tags);
            $matches = array_filter($terms, static fn (string $term): bool => array_filter($normalized, static fn (string $tag): bool => str_contains($tag, $term)) !== []);
            if (count($matches) === count($terms)) {
                $paths[] = $path;
            }
        }

        return $paths;
    }

    private function string(mixed $value, string $default = ''): string
    {
        return is_scalar($value) || $value instanceof \Stringable ? (string) $value : $default;
    }

    private function integer(mixed $value, int $default = 0): int
    {
        return filter_var($value, FILTER_VALIDATE_INT) !== false ? (int) $value : $default;
    }
}
