<?php

declare(strict_types=1);

namespace SohoPHP\SoFinder\Http\Action;

use SohoPHP\SoFinder\Contract\ImageCapabilityProviderInterface;
use SohoPHP\SoFinder\Feature\FeaturePolicy;
use SohoPHP\SoFinder\FileManager;
use SohoPHP\SoFinder\Http\EndpointActionInterface;
use SohoPHP\SoFinder\Http\EndpointResult;
use SohoPHP\SoFinder\Plugin\PluginRegistry;
use SohoPHP\SoFinder\Value\OperationResult;
use SohoPHP\SoFinder\Value\RequestContext;

final readonly class ConfigAction implements EndpointActionInterface
{
    public function __construct(
        private FileManager $files,
        private PluginRegistry $plugins,
        /** @var array<string, array{width:int,height:int,quality:int}> */
        private array $imagePresets = [],
        private ?ImageCapabilityProviderInterface $imageCapabilities = null,
        /** @var array{mode?:string,header?:bool,logo?:bool,search?:bool,language_switcher?:bool,view_switcher?:bool,folder_tree?:bool,scale?:string,upload_conflict_strategy?:string,lowercase_upload_extensions?:bool} */
        private array $ui = [],
        private FeaturePolicy $features = new FeaturePolicy(),
        private bool $signedUrlsEnabled = false,
        private int $signedUrlDefaultTtl = 300,
        private int $signedUrlMaxTtl = 3600,
        private bool $assetCatalogEnabled = false,
        private bool $imageVariantsEnabled = false,
        /** @var list<string> */
        private array $assetAltLocales = ['en', 'zh-cn', 'zh-tw'],
        private bool $assetSearchEnabled = true,
        private bool $assetUsageEnabled = false,
        private bool $assetAccessSessionsEnabled = false,
    ) {
    }

    public function endpoint(): string
    {
        return 'sofinder_api_config';
    }

    public function execute(RequestContext $context = new RequestContext(), array $input = []): EndpointResult
    {
        return new EndpointResult(OperationResult::success([
            'apiVersion' => '1.0',
            'resources' => $this->files->resources(),
            'plugins' => $this->plugins->descriptors(),
            'imagePresets' => $this->imagePresets,
            'imageCapabilities' => [
                'driver' => $this->imageCapabilities?->driver() ?? '',
                'formats' => $this->imageCapabilities?->capabilities() ?? [],
            ],
            'uiDefaults' => [
                'scale' => (string) ($this->ui['scale'] ?? 'standard'),
                'mode' => (string) ($this->ui['mode'] ?? 'auto'),
                'header' => (bool) ($this->ui['header'] ?? true),
                'logo' => (bool) ($this->ui['logo'] ?? true),
                'search' => (bool) ($this->ui['search'] ?? true),
                'languageSwitcher' => (bool) ($this->ui['language_switcher'] ?? true),
                'viewSwitcher' => (bool) ($this->ui['view_switcher'] ?? true),
                'uploadConflictStrategy' => (string) ($this->ui['upload_conflict_strategy'] ?? 'ask'),
                'lowercaseUploadExtensions' => (bool) ($this->ui['lowercase_upload_extensions'] ?? true),
            ],
            'featureAvailability' => $this->features->browserAvailability(),
            'signedUrls' => [
                'enabled' => $this->signedUrlsEnabled,
                'defaultTtlSeconds' => $this->signedUrlDefaultTtl,
                'maxTtlSeconds' => $this->signedUrlMaxTtl,
            ],
            'assetCatalog' => ['enabled' => $this->assetCatalogEnabled, 'altLocales' => $this->assetAltLocales],
            'assetSearch' => ['enabled' => $this->assetSearchEnabled],
            'assetUsage' => ['enabled' => $this->assetUsageEnabled],
            'assetAccessSessions' => ['enabled' => $this->assetAccessSessionsEnabled],
            'imageVariants' => ['enabled' => $this->imageVariantsEnabled],
        ])->jsonSerialize());
    }
}
