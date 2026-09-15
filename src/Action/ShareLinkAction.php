<?php

declare(strict_types=1);

namespace SohoPHP\SoFinder\Http\Action;

use SohoPHP\SoFinder\Contract\EndpointUrlGeneratorInterface;
use SohoPHP\SoFinder\Contract\ShareLinkProviderInterface;
use SohoPHP\SoFinder\FileManager;
use SohoPHP\SoFinder\Http\EndpointActionInterface;
use SohoPHP\SoFinder\Http\EndpointResult;
use SohoPHP\SoFinder\ResourceRegistry;
use SohoPHP\SoFinder\Security\SignedUrlManager;
use SohoPHP\SoFinder\Value\RequestContext;
use SohoPHP\SoFinder\Value\ShareDescriptor;

final class ShareLinkAction implements EndpointActionInterface
{
    /** @param iterable<ShareLinkProviderInterface> $providers */
    public function __construct(
        private readonly FileManager $files,
        private readonly ResourceRegistry $resources,
        private readonly SignedUrlManager $signedUrls,
        private readonly EndpointUrlGeneratorInterface $urls,
        private readonly bool $signedUrlsEnabled,
        private readonly iterable $providers = [],
    ) {}

    public function endpoint(): string { return 'sofinder_api_share_link'; }

    public function execute(RequestContext $context = new RequestContext(), array $input = []): EndpointResult
    {
        $resourceName = $this->string($context->query('resource'), 'Files');
        $path = $this->string($context->query('path'));
        $entry = $this->files->entry($resourceName, $path);
        $resource = $this->resources->get($resourceName)->resource;
        foreach ($this->providers as $provider) {
            $descriptor = $provider->share($resource, $entry);
            if ($descriptor !== null) return new EndpointResult(['success' => true, 'data' => $descriptor->jsonSerialize()]);
        }
        if ($resource->deliveryMode === 'public' && $entry->url !== null && $entry->url !== '') {
            $descriptor = new ShareDescriptor($entry->url);
        } elseif ($resource->deliveryMode === 'proxy' && $this->signedUrlsEnabled) {
            $issued = $this->signedUrls->issue($resourceName, $path, null, 'inline');
            $descriptor = new ShareDescriptor($this->urls->generate('sofinder_signed_content', ['token' => $issued['token']], true), expiresAt: $issued['expiresAt']);
        } else {
            $descriptor = new ShareDescriptor(
                $this->urls->generate('sofinder_api_content', ['resource' => $resourceName, 'path' => $path, 'disposition' => 'inline'], true),
                'login_required',
            );
        }

        return new EndpointResult(['success' => true, 'data' => $descriptor->jsonSerialize()]);
    }

    private function string(mixed $value, string $default = ''): string
    {
        return is_scalar($value) || $value instanceof \Stringable ? (string) $value : $default;
    }
}
