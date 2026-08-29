<?php

declare(strict_types=1);

namespace SohoPHP\SoFinder\Http\Action;

use SohoPHP\SoFinder\Contract\AssetSearchProviderInterface;
use SohoPHP\SoFinder\Exception\NotFoundException;
use SohoPHP\SoFinder\Exception\SoFinderException;
use SohoPHP\SoFinder\Http\EndpointActionInterface;
use SohoPHP\SoFinder\Http\EndpointResult;
use SohoPHP\SoFinder\Value\AssetSearchQuery;
use SohoPHP\SoFinder\Value\OperationResult;
use SohoPHP\SoFinder\Value\RequestContext;
use SohoPHP\SoFinder\Workspace\WorkspaceProvider;

final readonly class AssetSearchAction implements EndpointActionInterface
{
    public function __construct(
        private AssetSearchProviderInterface $search,
        private WorkspaceProvider $workspaces,
        private bool $enabled = true,
    ) {
    }

    public function endpoint(): string
    {
        return 'sofinder_api_asset_search';
    }

    public function execute(RequestContext $context = new RequestContext(), array $input = []): EndpointResult
    {
        if (!$this->enabled) {
            throw new NotFoundException('Asset search is disabled.');
        }
        $keyword = trim($this->string($context->query('q')));
        if (mb_strlen($keyword) > 200) {
            throw new SoFinderException('The search query is too long.', 'invalid_asset_search', 422);
        }
        $resources = $this->list($context, 'resources', '/^[A-Za-z][A-Za-z0-9_-]{0,63}$/D');
        $fields = $this->list($context, 'fields', '/^(?:name|title|alt|tags)$/D') ?: ['name', 'title', 'alt', 'tags'];
        $tags = $this->list($context, 'tags', '/^[^\x00-\x1F\x7F]{1,50}$/uD', 20);
        $extensions = array_map('strtolower', $this->list($context, 'extensions', '/^[A-Za-z0-9]{1,16}$/D', 30));
        $type = strtolower($this->string($context->query('type'), 'all'));
        if (!in_array($type, ['all', 'image', 'document', 'audio', 'video', 'archive', 'other'], true)) {
            throw new SoFinderException('The asset type filter is invalid.', 'invalid_asset_search', 422);
        }
        $minimumSize = $this->nullableInteger($context, 'minSize', 0);
        $maximumSize = $this->nullableInteger($context, 'maxSize', 0);
        $modifiedAfter = $this->nullableInteger($context, 'modifiedAfter', 0);
        $modifiedBefore = $this->nullableInteger($context, 'modifiedBefore', 0);
        if ($minimumSize !== null && $maximumSize !== null && $minimumSize > $maximumSize) {
            throw new SoFinderException('The asset size range is invalid.', 'invalid_asset_search', 422);
        }
        if ($modifiedAfter !== null && $modifiedBefore !== null && $modifiedAfter > $modifiedBefore) {
            throw new SoFinderException('The asset date range is invalid.', 'invalid_asset_search', 422);
        }
        $query = new AssetSearchQuery(
            $keyword,
            $resources,
            trim($this->string($context->query('path')), '/'),
            $fields,
            $tags,
            $extensions,
            $type,
            $minimumSize,
            $maximumSize,
            $modifiedAfter,
            $modifiedBefore,
            max(0, $this->integer($context->query('offset'))),
            max(1, min(200, $this->integer($context->query('limit'), 50))),
        );

        return new EndpointResult(OperationResult::success($this->search->search($this->workspaces->current($context), $query)->jsonSerialize())->jsonSerialize());
    }

    /** @return list<string> */
    private function list(RequestContext $context, string $name, string $pattern, int $maximum = 50): array
    {
        $rawValue = $context->query($name);
        $raw = is_array($rawValue) ? $rawValue : (is_string($rawValue) && $rawValue !== '' ? explode(',', $rawValue) : []);
        $values = array_values(array_unique(array_map('trim', array_filter($raw, 'is_string'))));
        if (count($values) > $maximum) {
            throw new SoFinderException('Too many asset search filters were supplied.', 'invalid_asset_search', 422);
        }
        foreach ($values as $value) {
            if (preg_match($pattern, $value) !== 1) {
                throw new SoFinderException('An asset search filter is invalid.', 'invalid_asset_search', 422);
            }
        }

        return $values;
    }

    private function nullableInteger(RequestContext $context, string $name, int $minimum): ?int
    {
        $value = $context->query($name);
        if ($value === null || $value === '') {
            return null;
        }
        if (filter_var($value, FILTER_VALIDATE_INT) === false || (int) $value < $minimum) {
            throw new SoFinderException('An asset search range is invalid.', 'invalid_asset_search', 422);
        }

        return (int) $value;
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
