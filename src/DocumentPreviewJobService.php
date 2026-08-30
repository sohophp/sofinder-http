<?php

declare(strict_types=1);

namespace SohoPHP\SoFinder\Http;

use SohoPHP\SoFinder\Contract\EndpointUrlGeneratorInterface;
use SohoPHP\SoFinder\Feature\FeaturePolicy;
use SohoPHP\SoFinder\Preview\DocumentPreviewJobManager;

final class DocumentPreviewJobService
{
    public function __construct(
        private readonly DocumentPreviewJobManager $jobs,
        private readonly EndpointUrlGeneratorInterface $urls,
        private readonly FeaturePolicy $features = new FeaturePolicy(),
    ) {
    }

    /** @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function create(array $input): array
    {
        $this->features->assertEnabled('document_preview');
        return $this->withUrl($this->jobs->prepare($this->string($input['resource'] ?? 'Files'), $this->string($input['path'] ?? ''), ($input['retry'] ?? false) === true));
    }

    /** @return array<string,mixed> */
    public function status(string $id): array
    {
        $this->features->assertEnabled('document_preview');
        return $this->withUrl($this->jobs->status($id));
    }

    /** @param array<string,mixed> $job
     * @return array<string,mixed>
     */
    private function withUrl(array $job): array
    {
        $job['previewUrl'] = $job['status'] === 'ready'
            ? $this->urls->generate('sofinder_document_preview', ['resource' => $job['resource'], 'path' => $job['path']])
            : null;

        return $job;
    }

    private function string(mixed $value): string
    {
        return is_scalar($value) || $value instanceof \Stringable ? (string) $value : '';
    }
}
