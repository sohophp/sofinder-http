<?php

declare(strict_types=1);

namespace SohoPHP\SoFinder\Http;

use Psr\EventDispatcher\EventDispatcherInterface;
use SohoPHP\SoFinder\Archive\ArchiveManager;
use SohoPHP\SoFinder\Asset\AssetAccessSessionManager;
use SohoPHP\SoFinder\Asset\AssetOperationPublisher;
use SohoPHP\SoFinder\Asset\AssetReferenceBuilder;
use SohoPHP\SoFinder\Asset\BoundedAssetSearchProvider;
use SohoPHP\SoFinder\Asset\JsonAssetAccessSessionStore;
use SohoPHP\SoFinder\Asset\JsonAssetCatalog;
use SohoPHP\SoFinder\Asset\JsonAssetUsageStore;
use SohoPHP\SoFinder\Contract\ActorProviderInterface;
use SohoPHP\SoFinder\Contract\AuthorizationInterface;
use SohoPHP\SoFinder\Contract\CsrfTokenProviderInterface;
use SohoPHP\SoFinder\Contract\DocumentPreviewDispatcherInterface;
use SohoPHP\SoFinder\Contract\EndpointUrlGeneratorInterface;
use SohoPHP\SoFinder\Contract\EntryUrlGeneratorInterface;
use SohoPHP\SoFinder\Contract\MaintenanceDispatcherInterface;
use SohoPHP\SoFinder\Contract\RequestContextProviderInterface;
use SohoPHP\SoFinder\Contract\RoleAuthorizationInterface;
use SohoPHP\SoFinder\Contract\StorageAdapterFactoryInterface;
use SohoPHP\SoFinder\FileManager;
use SohoPHP\SoFinder\Feature\FeaturePolicy;
use SohoPHP\SoFinder\Http\Action\BrowserAction;
use SohoPHP\SoFinder\Image\GdImageProcessor;
use SohoPHP\SoFinder\Image\HybridImageProcessor;
use SohoPHP\SoFinder\Image\ImageFormatRegistry;
use SohoPHP\SoFinder\Image\ImageManager;
use SohoPHP\SoFinder\Image\ImagickImageProcessor;
use SohoPHP\SoFinder\Maintenance\MaintenanceCoordinator;
use SohoPHP\SoFinder\Maintenance\MaintenanceRunner;
use SohoPHP\SoFinder\Metadata\JsonMetadataStore;
use SohoPHP\SoFinder\Metadata\MetadataManager;
use SohoPHP\SoFinder\Observability\LocalMetricsStore;
use SohoPHP\SoFinder\Preview\DocumentPreviewJobManager;
use SohoPHP\SoFinder\Preview\DocumentPreviewManager;
use SohoPHP\SoFinder\Security\DefaultFileInspector;
use SohoPHP\SoFinder\Security\MalwareScanStatusStore;
use SohoPHP\SoFinder\Security\PathGuard;
use SohoPHP\SoFinder\Security\SignedUrlManager;
use SohoPHP\SoFinder\Security\UploadPipeline;
use SohoPHP\SoFinder\Storage\ResourceRegistryFactory;
use SohoPHP\SoFinder\Storage\StoragePaginator;
use SohoPHP\SoFinder\Trash\TrashManager;
use SohoPHP\SoFinder\Upload\ChunkUploadManager;
use SohoPHP\SoFinder\Upload\UploadNamePolicy;
use SohoPHP\SoFinder\Usage\PersistentUsageTracker;
use SohoPHP\SoFinder\Value\Theme;
use SohoPHP\SoFinder\Workspace\DefaultWorkspaceResolver;
use SohoPHP\SoFinder\Workspace\WorkspaceProvider;

/** Complete framework-neutral local-storage service graph for PSR hosts. */
final class LocalRuntime
{
    /** @var list<EndpointActionInterface>|null */
    private ?array $actions = null;

    /**
     * @param array<string,mixed> $configuration A normalized ConfigurationNormalizer result.
     * @param iterable<StorageAdapterFactoryInterface> $storageFactories
     */
    public function __construct(
        private readonly array $configuration,
        private readonly AuthorizationInterface $authorization,
        private readonly ActorProviderInterface $actor,
        private readonly CsrfTokenProviderInterface $csrf,
        private readonly RoleAuthorizationInterface $roles,
        private readonly EventDispatcherInterface $events,
        private readonly EndpointUrlGeneratorInterface $endpointUrls,
        private readonly EntryUrlGeneratorInterface $entryUrls,
        private readonly RequestContextProviderInterface $requests,
        private readonly string $packageDirectory,
        private readonly iterable $storageFactories = [],
        private readonly ?MaintenanceDispatcherInterface $maintenanceDispatcher = null,
        private readonly ?DocumentPreviewDispatcherInterface $documentPreviewDispatcher = null,
    ) {
    }

    /** @return list<EndpointActionInterface> */
    public function actions(): array
    {
        if ($this->actions !== null) return $this->actions;
        $c = $this->configuration;
        $pathGuard = new PathGuard();
        $resources = (new ResourceRegistryFactory($pathGuard, $this->storageFactories))->create((array) $c['resources']);
        $workspaces = new WorkspaceProvider(
            new DefaultWorkspaceResolver($this->actor, $resources, (string) $c['workspaces']['default']),
            $this->requests,
        );
        $usage = new PersistentUsageTracker((string) $c['usage_dir']);
        $trash = new TrashManager((string) $c['trash_dir'], $this->actor, $pathGuard, (int) $c['trash_retention_days'], (int) $c['trash_max_items'], (int) $c['trash_max_bytes']);
        $chunks = new ChunkUploadManager((string) $c['chunk_dir'], $this->actor, (int) $c['chunk_size'], (int) $c['max_upload_chunks']);
        $maintenanceDirectory = rtrim((string) $c['cache_dir'], '/') . '/maintenance';
        $runner = new MaintenanceRunner($maintenanceDirectory, $chunks, $trash, $usage, $resources);
        $maintenance = new MaintenanceCoordinator(
            $maintenanceDirectory,
            (string) $c['maintenance']['mode'],
            (int) $c['maintenance']['min_interval_seconds'],
            (int) $c['maintenance']['max_items_per_run'],
            $runner,
            $this->maintenanceDispatcher,
        );
        $formats = new ImageFormatRegistry();
        $gd = new GdImageProcessor((int) $c['image_processing']['max_single_frame_pixels'], $formats);
        $imagick = new ImagickImageProcessor($formats);
        $imagesProcessor = new HybridImageProcessor($formats, $gd, $imagick, (string) $c['image_processing']['driver']);
        $uploads = new UploadPipeline(new DefaultFileInspector($imagesProcessor, $formats), (string) $c['quarantine_dir']);
        $files = new FileManager(
            $resources, $this->authorization, $this->events, $pathGuard, $uploads, $this->entryUrls,
            $trash, $usage, new StoragePaginator(), $maintenance, $workspaces,
        );
        $metadata = new MetadataManager(
            $files,
            new JsonMetadataStore((string) $c['metadata_file']),
            $this->actor,
            (bool) $c['features']['quick_access_files'],
            $workspaces,
        );
        $variantWidths = array_values(array_map('intval', (array) $c['image_variants']['widths']));
        $variantFormats = array_values(array_map('strval', (array) $c['image_variants']['formats']));
        $imageManager = new ImageManager(
            $files, $imagesProcessor, (string) $c['cache_dir'], (array) $c['image_presets'], $formats,
            (int) octdec((string) $c['filesystem_permissions']['directory_mode']), (int) octdec((string) $c['filesystem_permissions']['file_mode']),
            (bool) $c['image_variants']['enabled'], $variantWidths, $variantFormats,
            (int) $c['image_variants']['quality'], (int) $c['image_variants']['cache_ttl_seconds'],
        );
        $catalog = new JsonAssetCatalog(rtrim((string) $c['cache_dir'], '/') . '/assets.json');
        $assetUsages = new JsonAssetUsageStore(rtrim((string) $c['cache_dir'], '/') . '/asset-usages.json');
        $assetSessionsStore = new JsonAssetAccessSessionStore(rtrim((string) $c['cache_dir'], '/') . '/asset-access-sessions');
        $references = new AssetReferenceBuilder(
            $this->endpointUrls, $workspaces, $catalog, $imageManager,
            (bool) $c['asset_catalog']['enabled'], (bool) $c['image_variants']['enabled'],
            $variantWidths, $variantFormats,
        );
        $assetEvents = new AssetOperationPublisher($this->events, $workspaces, $resources, $catalog, (bool) $c['asset_catalog']['enabled']);
        $metrics = new LocalMetricsStore(rtrim((string) $c['cache_dir'], '/') . '/metrics.json');
        $previews = new DocumentPreviewManager(
            $files, (string) $c['cache_dir'], (bool) $c['document_preview']['pdf'], (bool) $c['document_preview']['office'],
            (string) $c['document_preview']['office_binary'], (int) $c['document_preview']['timeout_seconds'], (int) $c['document_preview']['max_bytes'], $metrics,
        );
        $previewJobs = new DocumentPreviewJobManager(
            $previews, $this->actor, rtrim((string) $c['cache_dir'], '/') . '/document-preview-jobs.json',
            (string) $c['document_preview']['mode'], (int) $c['document_preview']['job_ttl_seconds'],
            (int) $c['document_preview']['cache_ttl_seconds'], dispatcher: $this->documentPreviewDispatcher, metrics: $metrics,
        );
        $signed = new SignedUrlManager(
            $files, $resources, $pathGuard, (bool) $c['signed_urls']['enabled'], (string) $c['signed_urls']['secret'],
            (int) $c['signed_urls']['default_ttl_seconds'], (int) $c['signed_urls']['max_ttl_seconds'],
        );
        $health = (new LocalHealthManagerFactory())->create(
            $c,
            $resources,
            $imagesProcessor,
            $formats,
            $metrics,
            $this->packageDirectory,
            (string) $c['maintenance']['mode'] !== 'messenger' || $this->maintenanceDispatcher !== null,
        );
        $assetSessions = new AssetAccessSessionManager(
            $catalog, $assetSessionsStore, $workspaces, $files, $resources, (bool) $c['asset_access_sessions']['enabled'],
            (int) $c['asset_access_sessions']['default_ttl_seconds'], (int) $c['asset_access_sessions']['max_ttl_seconds'], (int) $c['asset_access_sessions']['max_assets'],
        );
        $names = new UploadNamePolicy((bool) $c['uploads']['naming']['lowercase_extensions']);
        $standard = new StandardEndpointActions($files, $metadata, $this->authorization, $this->csrf, $names, $c, $imagesProcessor);
        $advanced = new AdvancedEndpointActions(
            $files, $this->authorization, $this->csrf, $this->roles, $chunks, $maintenance, $names, $workspaces,
            $imageManager, $references, $assetEvents, new ArchiveManager($files, $pathGuard, (string) $c['cache_dir']),
            new BoundedAssetSearchProvider($files, $catalog, (int) $c['asset_search']['max_scanned_entries']),
            $catalog, $assetUsages, $assetSessions, $previews, $previewJobs, $signed, $this->endpointUrls,
            $health, $metrics,
            new MalwareScanStatusStore(rtrim((string) $c['cache_dir'], '/') . '/malware-scans.json', (int) $c['malware_scanning']['history_limit']),
            $this->packageDirectory, $c,
        );
        $browser = new BrowserAction(new BrowserPage(
            $files,
            $this->endpointUrls,
            $this->csrf,
            $this->assetVersion(),
            new Theme((array) $c['theme']),
            (array) $c['ui'],
            new FeaturePolicy((array) $c['features']),
            $this->roles,
            array_values(array_filter((array) $c['malware_scanning']['status_roles'], 'is_string')),
            array_values(array_filter((array) $c['picker']['allowed_origins'], 'is_string')),
            $workspaces,
        ));

        return $this->actions = [$browser, ...$standard->all(), ...$advanced->all()];
    }

    private function assetVersion(): string
    {
        $fingerprint = hash_init('sha256');
        foreach (['sofinder.js', 'sofinder-picker.js', 'sofinder.css'] as $file) {
            $path = $this->packageDirectory . '/dist/' . $file;
            if (is_file($path)) hash_update_file($fingerprint, $path);
        }

        return substr(hash_final($fingerprint), 0, 12);
    }
}
