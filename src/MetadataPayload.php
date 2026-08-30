<?php

declare(strict_types=1);

namespace SohoPHP\SoFinder\Http;

use SohoPHP\SoFinder\Feature\FeaturePolicy;
use SohoPHP\SoFinder\Metadata\MetadataManager;

final class MetadataPayload
{
    public function __construct(private readonly MetadataManager $metadata, private readonly FeaturePolicy $features = new FeaturePolicy())
    {
    }

    public function assertAvailable(): void
    {
        if (!$this->features->enabled('recent') && !$this->features->enabled('favorites') && !$this->features->enabled('quick_access') && !$this->features->enabled('tags')) {
            $this->features->assertEnabled('recent');
        }
    }

    public function assertAction(string $action): void
    {
        $this->features->assertEnabled(match ($action) {
            'favorite' => 'favorites',
            'quick_access' => 'quick_access',
            'tags' => 'tags',
            'touch', 'forget' => 'recent',
            default => throw new \SohoPHP\SoFinder\Exception\SoFinderException('The metadata action is invalid.', 'invalid_metadata_action', 400),
        });
    }

    /** @return array<string, mixed> */
    public function forResource(string $resource): array
    {
        $metadata = $this->metadata->get($resource);
        if (!$this->features->enabled('recent')) {
            $metadata['recent'] = [];
        }
        if (!$this->features->enabled('favorites')) {
            $metadata['favorites'] = [];
        }
        if (!$this->features->enabled('quick_access')) {
            $metadata['quickAccess'] = [];
        }
        if (!$this->features->enabled('tags')) {
            $metadata['tags'] = [];
        }
        $metadata['quickAccessEntries'] = $this->features->enabled('quick_access') ? $this->metadata->quickAccessEntries($resource) : [];

        return $metadata;
    }
}
