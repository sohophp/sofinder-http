<?php

declare(strict_types=1);

namespace SohoPHP\SoFinder\Http\Action;

use SohoPHP\SoFinder\Contract\MalwareScanStatusStoreInterface;
use SohoPHP\SoFinder\Contract\RoleAuthorizationInterface;
use SohoPHP\SoFinder\Exception\AccessDeniedException;
use SohoPHP\SoFinder\Feature\FeaturePolicy;
use SohoPHP\SoFinder\Http\EndpointActionInterface;
use SohoPHP\SoFinder\Http\EndpointResult;
use SohoPHP\SoFinder\Preview\DocumentPreviewJobManager;
use SohoPHP\SoFinder\Preview\DocumentPreviewManager;
use SohoPHP\SoFinder\Security\ClamAvScanner;
use SohoPHP\SoFinder\Value\RequestContext;

final class SecurityStatusAction implements EndpointActionInterface
{
    /** @param list<string> $roles */
    public function __construct(
        private readonly bool $enabled,
        private readonly MalwareScanStatusStoreInterface $scans,
        private readonly ?ClamAvScanner $scanner = null,
        private readonly ?RoleAuthorizationInterface $authorization = null,
        private readonly array $roles = [],
        private readonly FeaturePolicy $features = new FeaturePolicy(),
        private readonly ?DocumentPreviewManager $documentPreviews = null,
        private readonly ?DocumentPreviewJobManager $documentPreviewJobs = null,
    ) {
    }

    public function endpoint(): string { return 'sofinder_security_status'; }

    public function execute(RequestContext $context = new RequestContext(), array $input = []): EndpointResult
    {
        $this->features->assertEnabled('security_status');
        if ($this->roles !== [] && ($this->authorization === null || array_filter($this->roles, $this->authorization->isGranted(...)) === [])) {
            throw new AccessDeniedException('The security status requires an administrator role.');
        }
        $health = $this->scanner?->check();
        $report = $this->scans->report();
        $document = $this->documentPreviews?->diagnostics();
        $jobs = $this->documentPreviewJobs?->diagnostics();

        return new EndpointResult(['success' => true, 'data' => [
            'malwareScanning' => [
                'enabled' => $this->enabled,
                'provider' => $this->enabled ? 'clamav' : null,
                'status' => !$this->enabled ? 'disabled' : ($health === null ? 'down' : $health->status),
                'message' => !$this->enabled ? 'Malware scanning is not enabled.' : ($health === null ? 'ClamAV is unavailable.' : $health->message),
                'counts' => $report['counts'], 'recent' => $report['recent'], 'mode' => $report['mode'], 'lastSuccessfulAt' => $report['lastSuccessfulAt'],
            ],
            'documentPreview' => $document === null ? null : [...$document, ...($jobs ?? [])],
        ]]);
    }
}
