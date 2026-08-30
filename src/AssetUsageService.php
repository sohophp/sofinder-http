<?php

declare(strict_types=1);

namespace SohoPHP\SoFinder\Http;

use SohoPHP\SoFinder\Contract\AssetCatalogInterface;
use SohoPHP\SoFinder\Contract\AssetUsageStoreInterface;
use SohoPHP\SoFinder\Exception\NotFoundException;
use SohoPHP\SoFinder\Exception\SoFinderException;
use SohoPHP\SoFinder\FileManager;
use SohoPHP\SoFinder\Value\AssetRecord;
use SohoPHP\SoFinder\Value\RequestContext;
use SohoPHP\SoFinder\Workspace\WorkspaceProvider;

final class AssetUsageService
{
    public function __construct(
        private readonly AssetCatalogInterface $catalog,
        private readonly AssetUsageStoreInterface $usages,
        private readonly WorkspaceProvider $workspaces,
        private readonly FileManager $files,
        private readonly bool $enabled,
    ) {
    }

    /** @return array{items:list<array{referenceId:string,label:string,url:?string,context:?string,updatedAt:int}>,total:int} */
    public function list(RequestContext $context, string $id): array
    {
        $record = $this->record($context, $id);
        $this->files->assertOperation($record->resource, 'read', $record->path);
        $items = $this->usages->list($record->workspace, $id);

        return ['items' => $items, 'total' => count($items)];
    }

    /** @param array<string, mixed> $input
     * @return array{usage:array{referenceId:string,label:string,url:?string,context:?string,updatedAt:int}}
     */
    public function put(RequestContext $context, string $id, string $referenceId, array $input): array
    {
        $record = $this->record($context, $id);
        $this->files->assertOperation($record->resource, 'read', $record->path);
        $referenceId = trim($referenceId);
        $label = trim($this->string($input['label'] ?? ''));
        $url = array_key_exists('url', $input) ? trim($this->string($input['url'])) : null;
        $usageContext = array_key_exists('context', $input) ? trim($this->string($input['context'])) : null;
        if (preg_match('/^[A-Za-z0-9._:-]{1,160}$/D', $referenceId) !== 1 || $label === '' || mb_strlen($label) > 200 || ($url !== null && mb_strlen($url) > 2000) || ($usageContext !== null && mb_strlen($usageContext) > 100)) {
            throw new SoFinderException('The asset usage reference is invalid.', 'invalid_asset_usage', 422);
        }

        return ['usage' => $this->usages->put($record->workspace, $id, $referenceId, $label, $url ?: null, $usageContext ?: null)];
    }

    public function remove(RequestContext $context, string $id, string $referenceId): void
    {
        $record = $this->record($context, $id);
        $this->files->assertOperation($record->resource, 'read', $record->path);
        $this->usages->remove($record->workspace, $id, $referenceId);
    }

    /** @param array<string, mixed> $input
     * @return array{safe:bool,complete:bool,total:int,assets:list<array{assetId:string,path:string,usages:list<array{referenceId:string,label:string,url:?string,context:?string,updatedAt:int}>,total:int}>}
     */
    public function deleteCheck(RequestContext $context, array $input): array
    {
        $this->assertEnabled();
        $resource = trim($this->string($input['resource'] ?? ''));
        $workspace = $this->workspaces->assertResource($resource, $context);
        $paths = array_values(array_unique(array_filter(is_array($input['paths'] ?? null) ? $input['paths'] : [], 'is_string')));
        if ($paths === [] || count($paths) > 1000) {
            throw new SoFinderException('One to 1000 paths are required.', 'invalid_paths', 422);
        }
        $assets = [];
        $total = 0;
        $complete = true;
        $scanned = 0;
        foreach ($paths as $path) {
            $this->files->assertOperation($resource, 'delete', $path, true);
            $entry = $this->files->entry($resource, $path);
            $candidatePaths = [$path];
            if ($entry->directory) {
                $directories = [$path];
                while ($directories !== [] && $complete) {
                    $directory = array_shift($directories);
                    if (!is_string($directory)) {
                        continue;
                    }
                    $offset = 0;
                    $cursor = null;
                    do {
                        $page = $this->files->list($resource, $directory, '', 'name', 'asc', $offset, 500, cursor: $cursor);
                        foreach ($page['entries'] as $child) {
                            if (++$scanned > 10000) {
                                $complete = false;
                                break 2;
                            }
                            if ($child->directory) {
                                $directories[] = $child->path;
                            } else {
                                $candidatePaths[] = $child->path;
                            }
                        }
                        $offset += count($page['entries']);
                        $cursor = $page['nextCursor'];
                    } while ($cursor !== null || ($page['total'] !== null && $offset < $page['total']));
                }
            }
            foreach ($candidatePaths as $candidatePath) {
                $record = $this->catalog->resolve($workspace->id, $resource, $candidatePath);
                if ($record === null) {
                    continue;
                }
                $items = $this->usages->list($workspace->id, $record->id);
                if ($items === []) {
                    continue;
                }
                $assets[] = ['assetId' => $record->id, 'path' => $candidatePath, 'usages' => $items, 'total' => count($items)];
                $total += count($items);
            }
        }

        return ['safe' => $total === 0 && $complete, 'complete' => $complete, 'total' => $total, 'assets' => $assets];
    }

    private function record(RequestContext $context, string $id): AssetRecord
    {
        $this->assertEnabled();
        if (preg_match('/^[a-f0-9-]{36}$/D', $id) !== 1) {
            throw new NotFoundException();
        }
        $record = $this->catalog->find($id);
        if ($record === null || $record->deleted || $record->workspace !== $this->workspaces->assertResource($record->resource, $context)->id) {
            throw new NotFoundException();
        }

        return $record;
    }

    private function assertEnabled(): void
    {
        if (!$this->enabled) {
            throw new NotFoundException('Asset usage tracking is disabled.');
        }
    }

    private function string(mixed $value): string
    {
        return is_scalar($value) || $value instanceof \Stringable ? (string) $value : '';
    }
}
