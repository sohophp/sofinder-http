<?php

declare(strict_types=1);

namespace SohoPHP\SoFinder\Http;

use SohoPHP\SoFinder\Asset\AssetOperationPublisher;
use SohoPHP\SoFinder\Asset\AssetReferenceBuilder;
use SohoPHP\SoFinder\Contract\AssetCatalogInterface;
use SohoPHP\SoFinder\Contract\LocalizedAssetMetadataCatalogInterface;
use SohoPHP\SoFinder\Exception\NotFoundException;
use SohoPHP\SoFinder\Exception\SoFinderException;
use SohoPHP\SoFinder\FileManager;
use SohoPHP\SoFinder\Value\AssetRecord;
use SohoPHP\SoFinder\Value\RequestContext;
use SohoPHP\SoFinder\Workspace\WorkspaceProvider;

final class AssetService
{
    public function __construct(
        private readonly FileManager $files,
        private readonly AssetReferenceBuilder $references,
        private readonly AssetCatalogInterface $catalog,
        private readonly WorkspaceProvider $workspaces,
        private readonly bool $enabled,
        private readonly ?AssetOperationPublisher $events = null,
    ) {
    }

    /** @return array{asset:array<string,mixed>} */
    public function resolve(RequestContext $context): array
    {
        $this->assertEnabled();
        $resource = $this->string($context->query('resource'));
        $path = $this->string($context->query('path'));

        return ['asset' => $this->references->create($resource, $this->files->entry($resource, $path), context: $context)];
    }

    /** @return array{asset:array<string,mixed>,metadata:array<string,mixed>} */
    public function get(RequestContext $context, string $id): array
    {
        $record = $this->record($context, $id);
        $entry = $this->files->entry($record->resource, $record->path);

        return ['asset' => $this->references->create($record->resource, $entry, context: $context), 'metadata' => $record->metadata()];
    }

    /** @param array<string,mixed> $input
     * @return array{metadata:array<string,mixed>}
     */
    public function update(RequestContext $context, string $id, array $input): array
    {
        $record = $this->record($context, $id);
        $this->files->assertOperation($record->resource, 'metadata.update', $record->path, true);
        $alt = array_key_exists('alt', $input) && $input['alt'] !== null ? trim($this->string($input['alt'])) : null;
        $altTranslations = array_key_exists('altTranslations', $input) ? $this->altTranslations($input['altTranslations']) : null;
        $title = array_key_exists('title', $input) && $input['title'] !== null ? trim($this->string($input['title'])) : null;
        $tags = array_values(array_unique(array_map('trim', array_filter(is_array($input['tags'] ?? null) ? $input['tags'] : [], 'is_string'))));
        if (($alt !== null && mb_strlen($alt) > 1000) || ($title !== null && mb_strlen($title) > 200) || count($tags) > 20 || array_filter($tags, static fn (string $tag): bool => $tag === '' || mb_strlen($tag) > 50) !== []) {
            throw new SoFinderException('The asset metadata is invalid.', 'invalid_asset_metadata', 422);
        }
        $operationId = $this->events?->operationId();
        if ($operationId !== null) {
            $this->events?->dispatch($operationId, 'metadata.update', 'before', $record->resource, $record->path, assetId: $id, attributes: ['metadataVersion' => (int) ($input['version'] ?? 0)], context: $context);
        }
        try {
            if ($altTranslations !== null) {
                if (!$this->catalog instanceof LocalizedAssetMetadataCatalogInterface) {
                    throw new SoFinderException('The configured asset catalog does not support translated alternative text.', 'asset_metadata_translation_unsupported', 422);
                }
                $updated = $this->catalog->updateLocalizedMetadata($id, $alt, $title, $tags, (int) ($input['version'] ?? 0), $altTranslations);
            } else {
                $updated = $this->catalog->updateMetadata($id, $alt, $title, $tags, (int) ($input['version'] ?? 0));
            }
        } catch (\Throwable $error) {
            if ($operationId !== null) {
                $this->events?->dispatch($operationId, 'metadata.update', 'failed', $record->resource, $record->path, assetId: $id, attributes: ['errorCode' => $this->events?->errorCode($error) ?? 'operation_failed'], context: $context);
            }
            throw $error;
        }
        if ($operationId !== null) {
            $this->events?->dispatch($operationId, 'metadata.update', 'after', $record->resource, $record->path, assetId: $id, attributes: ['metadataVersion' => $updated->metadataVersion], context: $context);
        }

        return ['metadata' => $updated->metadata()];
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

    /** @return array<string,string> */
    private function altTranslations(mixed $value): array
    {
        if (!is_array($value) || count($value) > 20) {
            throw new SoFinderException('The translated alternative text is invalid.', 'invalid_asset_metadata', 422);
        }
        $translations = [];
        foreach ($value as $locale => $translation) {
            if (!is_string($locale) || !is_string($translation)) {
                throw new SoFinderException('The translated alternative text is invalid.', 'invalid_asset_metadata', 422);
            }
            $locale = strtolower(trim($locale));
            $translation = trim($translation);
            if (preg_match('/^[a-z]{2,3}(?:-[a-z0-9]{2,8})*$/D', $locale) !== 1 || mb_strlen($translation) > 1000) {
                throw new SoFinderException('The translated alternative text is invalid.', 'invalid_asset_metadata', 422);
            }
            $translations[$locale] = $translation;
        }
        ksort($translations);

        return $translations;
    }

    private function assertEnabled(): void
    {
        if (!$this->enabled) {
            throw new NotFoundException('The asset catalog is disabled.');
        }
    }

    private function string(mixed $value): string
    {
        return is_scalar($value) || $value instanceof \Stringable ? (string) $value : '';
    }
}
