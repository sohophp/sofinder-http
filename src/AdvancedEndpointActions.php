<?php

declare(strict_types=1);

namespace SohoPHP\SoFinder\Http;

use SohoPHP\SoFinder\Archive\ArchiveManager;
use SohoPHP\SoFinder\Asset\AssetAccessSessionManager;
use SohoPHP\SoFinder\Asset\AssetOperationPublisher;
use SohoPHP\SoFinder\Asset\AssetReferenceBuilder;
use SohoPHP\SoFinder\Contract\AssetCatalogInterface;
use SohoPHP\SoFinder\Contract\AssetSearchProviderInterface;
use SohoPHP\SoFinder\Contract\AssetUsageStoreInterface;
use SohoPHP\SoFinder\Contract\AuthorizationInterface;
use SohoPHP\SoFinder\Contract\ChunkUploadStoreInterface;
use SohoPHP\SoFinder\Contract\CsrfTokenProviderInterface;
use SohoPHP\SoFinder\Contract\EndpointUrlGeneratorInterface;
use SohoPHP\SoFinder\Contract\MalwareScanStatusStoreInterface;
use SohoPHP\SoFinder\Contract\MetricsStoreInterface;
use SohoPHP\SoFinder\Contract\RoleAuthorizationInterface;
use SohoPHP\SoFinder\Feature\FeaturePolicy;
use SohoPHP\SoFinder\FileManager;
use SohoPHP\SoFinder\Health\HealthManager;
use SohoPHP\SoFinder\Http\Action\ArchiveDownloadAction;
use SohoPHP\SoFinder\Http\Action\AssetDeleteCheckAction;
use SohoPHP\SoFinder\Http\Action\AssetGetAction;
use SohoPHP\SoFinder\Http\Action\AssetResolveAction;
use SohoPHP\SoFinder\Http\Action\AssetSearchAction;
use SohoPHP\SoFinder\Http\Action\AssetSessionContentAction;
use SohoPHP\SoFinder\Http\Action\AssetSessionCreateAction;
use SohoPHP\SoFinder\Http\Action\AssetSessionRevokeAction;
use SohoPHP\SoFinder\Http\Action\AssetUpdateAction;
use SohoPHP\SoFinder\Http\Action\AssetUsageListAction;
use SohoPHP\SoFinder\Http\Action\AssetUsagePutAction;
use SohoPHP\SoFinder\Http\Action\AssetUsageRemoveAction;
use SohoPHP\SoFinder\Http\Action\CancelChunkAction;
use SohoPHP\SoFinder\Http\Action\ChunkStatusAction;
use SohoPHP\SoFinder\Http\Action\ChunkUploadAction;
use SohoPHP\SoFinder\Http\Action\DocumentPreviewAction;
use SohoPHP\SoFinder\Http\Action\DocumentPreviewJobCreateAction;
use SohoPHP\SoFinder\Http\Action\DocumentPreviewJobStatusAction;
use SohoPHP\SoFinder\Http\Action\FrontendAssetAction;
use SohoPHP\SoFinder\Http\Action\HealthAction;
use SohoPHP\SoFinder\Http\Action\ImageBatchAction;
use SohoPHP\SoFinder\Http\Action\ImageEditAction;
use SohoPHP\SoFinder\Http\Action\ImageInfoAction;
use SohoPHP\SoFinder\Http\Action\ImageThumbnailAction;
use SohoPHP\SoFinder\Http\Action\ImageVariantAction;
use SohoPHP\SoFinder\Http\Action\MetricsAction;
use SohoPHP\SoFinder\Http\Action\SecurityStatusAction;
use SohoPHP\SoFinder\Http\Action\SignedContentAction;
use SohoPHP\SoFinder\Http\Action\SignedUrlIssueAction;
use SohoPHP\SoFinder\Image\ImageManager;
use SohoPHP\SoFinder\Maintenance\MaintenanceCoordinator;
use SohoPHP\SoFinder\Preview\DocumentPreviewJobManager;
use SohoPHP\SoFinder\Preview\DocumentPreviewManager;
use SohoPHP\SoFinder\Security\SignedUrlManager;
use SohoPHP\SoFinder\Upload\UploadNamePolicy;
use SohoPHP\SoFinder\Workspace\WorkspaceProvider;

/** Shared construction of advanced and optional HTTP use cases. */
final class AdvancedEndpointActions
{
    /** @param array<string,mixed> $configuration */
    public function __construct(
        private readonly FileManager $files,
        private readonly AuthorizationInterface $authorization,
        private readonly CsrfTokenProviderInterface $csrf,
        private readonly RoleAuthorizationInterface $roles,
        private readonly ChunkUploadStoreInterface $chunks,
        private readonly MaintenanceCoordinator $maintenance,
        private readonly UploadNamePolicy $uploadNames,
        private readonly WorkspaceProvider $workspaces,
        private readonly ImageManager $images,
        private readonly AssetReferenceBuilder $references,
        private readonly AssetOperationPublisher $assetEvents,
        private readonly ArchiveManager $archives,
        private readonly AssetSearchProviderInterface $assetSearch,
        private readonly AssetCatalogInterface $assets,
        private readonly AssetUsageStoreInterface $assetUsages,
        private readonly AssetAccessSessionManager $assetSessions,
        private readonly DocumentPreviewManager $previews,
        private readonly DocumentPreviewJobManager $previewJobs,
        private readonly SignedUrlManager $signedUrls,
        private readonly EndpointUrlGeneratorInterface $urls,
        private readonly HealthManager $health,
        private readonly MetricsStoreInterface $metrics,
        private readonly MalwareScanStatusStoreInterface $malwareScans,
        private readonly string $packageDirectory,
        private readonly array $configuration,
    ) {
    }

    /** @return list<EndpointActionInterface> */
    public function all(): array
    {
        $features = new FeaturePolicy((array) ($this->configuration['features'] ?? []));
        $guard = new MutationGuard($this->authorization, $this->csrf);
        $cached = new CachedFileResponseBuilder();
        $streams = new EntryStreamResponseBuilder();
        $catalogConfig = (array) ($this->configuration['asset_catalog'] ?? []);
        $searchConfig = (array) ($this->configuration['asset_search'] ?? []);
        $usageConfig = (array) ($this->configuration['asset_usage'] ?? []);
        $malware = (array) ($this->configuration['malware_scanning'] ?? []);

        $imageOperations = new ImageMutationService($this->assetEvents);
        $assetService = new AssetService($this->files, $this->references, $this->assets, $this->workspaces, (bool) ($catalogConfig['enabled'] ?? false), $this->assetEvents);
        $usageService = new AssetUsageService($this->assets, $this->assetUsages, $this->workspaces, $this->files, (bool) ($usageConfig['enabled'] ?? false));
        $jobService = new DocumentPreviewJobService($this->previewJobs, $this->urls, $features);

        return [
            new ChunkUploadAction($this->files, $this->chunks, $guard, $this->uploadNames, $this->maintenance, $this->references, $this->workspaces),
            new CancelChunkAction($this->chunks, $guard, $this->workspaces),
            new ChunkStatusAction($this->files, $this->chunks, $this->workspaces),
            new SignedUrlIssueAction($this->signedUrls, $this->urls),
            new SignedContentAction($this->signedUrls, $streams),
            new DocumentPreviewAction($this->previews, $features, $this->previewJobs),
            new DocumentPreviewJobCreateAction($jobService, $guard),
            new DocumentPreviewJobStatusAction($jobService),
            new ImageThumbnailAction($this->images, $cached),
            new ImageInfoAction($this->images),
            new ImageVariantAction($this->images, $cached),
            new ImageEditAction($this->images, $guard, $features, $imageOperations),
            new ImageBatchAction($this->images, $guard, $features, $imageOperations),
            new ArchiveDownloadAction($this->archives, $guard, $features),
            new AssetResolveAction($assetService),
            new AssetSearchAction($this->assetSearch, $this->workspaces, (bool) ($searchConfig['enabled'] ?? true)),
            new AssetGetAction($assetService),
            new AssetUpdateAction($assetService, $guard),
            new AssetUsageListAction($usageService),
            new AssetUsagePutAction($usageService, $guard),
            new AssetUsageRemoveAction($usageService, $guard),
            new AssetDeleteCheckAction($usageService, $guard),
            new AssetSessionCreateAction($this->assetSessions, $guard, $this->urls),
            new AssetSessionRevokeAction($this->assetSessions, $guard),
            new AssetSessionContentAction($this->assetSessions, $streams),
            new FrontendAssetAction($this->packageDirectory, $cached),
            new HealthAction($this->health),
            new MetricsAction($this->metrics, $this->health),
            new SecurityStatusAction(
                (bool) ($malware['enabled'] ?? false),
                $this->malwareScans,
                null,
                $this->roles,
                array_values(array_filter((array) ($malware['status_roles'] ?? []), 'is_string')),
                $features,
                $this->previews,
                $this->previewJobs,
            ),
        ];
    }
}
