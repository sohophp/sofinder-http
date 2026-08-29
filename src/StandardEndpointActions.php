<?php

declare(strict_types=1);

namespace SohoPHP\SoFinder\Http;

use SohoPHP\SoFinder\Contract\AuthorizationInterface;
use SohoPHP\SoFinder\Contract\CsrfTokenProviderInterface;
use SohoPHP\SoFinder\Contract\ImageCapabilityProviderInterface;
use SohoPHP\SoFinder\Feature\FeaturePolicy;
use SohoPHP\SoFinder\FileManager;
use SohoPHP\SoFinder\Http\Action\BatchAction;
use SohoPHP\SoFinder\Http\Action\BatchRenameAction;
use SohoPHP\SoFinder\Http\Action\CapabilityAction;
use SohoPHP\SoFinder\Http\Action\ChecksumAction;
use SohoPHP\SoFinder\Http\Action\ConfigAction;
use SohoPHP\SoFinder\Http\Action\ContentAction;
use SohoPHP\SoFinder\Http\Action\CreateFolderAction;
use SohoPHP\SoFinder\Http\Action\DeleteAction;
use SohoPHP\SoFinder\Http\Action\DeleteTrashAction;
use SohoPHP\SoFinder\Http\Action\DownloadAction;
use SohoPHP\SoFinder\Http\Action\EntriesAction;
use SohoPHP\SoFinder\Http\Action\LivenessAction;
use SohoPHP\SoFinder\Http\Action\MetadataGetAction;
use SohoPHP\SoFinder\Http\Action\MetadataUpdateAction;
use SohoPHP\SoFinder\Http\Action\QuickUploadAction;
use SohoPHP\SoFinder\Http\Action\RenameAction;
use SohoPHP\SoFinder\Http\Action\RestoreTrashAction;
use SohoPHP\SoFinder\Http\Action\TextPreviewAction;
use SohoPHP\SoFinder\Http\Action\TransferAction;
use SohoPHP\SoFinder\Http\Action\TrashListAction;
use SohoPHP\SoFinder\Http\Action\UploadAction;
use SohoPHP\SoFinder\Metadata\MetadataManager;
use SohoPHP\SoFinder\Plugin\PluginRegistry;
use SohoPHP\SoFinder\Upload\UploadNamePolicy;
use SohoPHP\SoFinder\Value\CapabilityCatalog;

/** Shared construction of the baseline file-management HTTP use cases. */
final readonly class StandardEndpointActions
{
    /** @param array<string,mixed> $configuration */
    public function __construct(
        private FileManager $files,
        private MetadataManager $metadata,
        private AuthorizationInterface $authorization,
        private CsrfTokenProviderInterface $csrf,
        private UploadNamePolicy $uploadNames,
        private array $configuration,
        private ?ImageCapabilityProviderInterface $imageCapabilities = null,
        private PluginRegistry $plugins = new PluginRegistry([]),
        private CapabilityCatalog $capabilities = new CapabilityCatalog(),
    ) {
    }

    /** @return list<EndpointActionInterface> */
    public function all(): array
    {
        $features = new FeaturePolicy((array) ($this->configuration['features'] ?? []));
        $guard = new MutationGuard($this->authorization, $this->csrf);
        $uploadGuard = new CompatibleUploadGuard($this->authorization, $this->csrf);
        $metadata = new MetadataPayload($this->metadata, $features);
        $streams = new EntryStreamResponseBuilder();
        $signed = (array) ($this->configuration['signed_urls'] ?? []);
        $catalog = (array) ($this->configuration['asset_catalog'] ?? []);
        $search = (array) ($this->configuration['asset_search'] ?? []);
        $usage = (array) ($this->configuration['asset_usage'] ?? []);
        $sessions = (array) ($this->configuration['asset_access_sessions'] ?? []);
        $variants = (array) ($this->configuration['image_variants'] ?? []);

        return [
            new LivenessAction(),
            new CapabilityAction($this->capabilities),
            new ConfigAction(
                $this->files,
                $this->plugins,
                (array) ($this->configuration['image_presets'] ?? []),
                $this->imageCapabilities,
                (array) ($this->configuration['ui'] ?? []),
                $features,
                (bool) ($signed['enabled'] ?? false),
                (int) ($signed['default_ttl_seconds'] ?? 300),
                (int) ($signed['max_ttl_seconds'] ?? 3600),
                (bool) ($catalog['enabled'] ?? false),
                (bool) ($variants['enabled'] ?? false),
                array_values(array_filter((array) ($catalog['alt_locales'] ?? []), 'is_string')),
                (bool) ($search['enabled'] ?? true),
                (bool) ($usage['enabled'] ?? false),
                (bool) ($sessions['enabled'] ?? false),
            ),
            new EntriesAction($this->files, $this->metadata, $features),
            new QuickUploadAction($this->files, $uploadGuard, $this->uploadNames, $this->imageCapabilities, (bool) ($this->configuration['ckeditor4']['overwrite_on_upload'] ?? false)),
            new CreateFolderAction($this->files, $guard),
            new RenameAction($this->files, $guard),
            new TransferAction($this->files, $guard, 'copy'),
            new TransferAction($this->files, $guard, 'move'),
            new DeleteAction($this->files, $guard),
            new BatchAction($this->files, $guard),
            new BatchRenameAction($this->files, $guard, $features),
            new TrashListAction($this->files, $features),
            new RestoreTrashAction($this->files, $guard, $features),
            new DeleteTrashAction($this->files, $guard, $features),
            new MetadataGetAction($metadata),
            new MetadataUpdateAction($this->metadata, $metadata, $guard),
            new ChecksumAction($this->files, $features),
            new TextPreviewAction($this->files, $features),
            new DownloadAction($this->files, $streams),
            new ContentAction($this->files, $streams),
            new UploadAction($this->files, $guard, $this->uploadNames),
        ];
    }
}
