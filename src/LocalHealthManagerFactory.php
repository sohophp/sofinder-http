<?php

declare(strict_types=1);

namespace SohoPHP\SoFinder\Http;

use SohoPHP\SoFinder\Contract\GaugeMetricsStoreInterface;
use SohoPHP\SoFinder\Contract\ImageCapabilityProviderInterface;
use SohoPHP\SoFinder\Health\DocumentPreviewHealthCheck;
use SohoPHP\SoFinder\Health\HealthManager;
use SohoPHP\SoFinder\Health\ImageHealthCheck;
use SohoPHP\SoFinder\Health\MaintenanceQueueHealthCheck;
use SohoPHP\SoFinder\Health\RuntimeHealthCheck;
use SohoPHP\SoFinder\Health\StorageHealthCheck;
use SohoPHP\SoFinder\Image\ImageFormatRegistry;
use SohoPHP\SoFinder\ResourceRegistry;

/** Builds the common readiness probes used by non-container local runtimes. */
final class LocalHealthManagerFactory
{
    /** @param array<string,mixed> $configuration */
    public function create(
        array $configuration,
        ResourceRegistry $resources,
        ImageCapabilityProviderInterface $images,
        ImageFormatRegistry $formats,
        GaugeMetricsStoreInterface $metrics,
        string $packageDirectory,
        bool $maintenanceDispatcherAvailable,
    ): HealthManager {
        $checks = [
            new RuntimeHealthCheck(
                [
                    (string) $configuration['cache_dir'],
                    (string) $configuration['quarantine_dir'],
                    (string) $configuration['chunk_dir'],
                    (string) $configuration['trash_dir'],
                    (string) $configuration['usage_dir'],
                    dirname((string) $configuration['metadata_file']),
                ],
                [
                    rtrim($packageDirectory, '/') . '/dist/sofinder.js',
                    rtrim($packageDirectory, '/') . '/dist/sofinder-picker.js',
                    rtrim($packageDirectory, '/') . '/dist/sofinder.css',
                ],
            ),
            new StorageHealthCheck($resources, $metrics),
            new ImageHealthCheck($images, $formats, $resources),
            new MaintenanceQueueHealthCheck(
                (string) $configuration['maintenance']['mode'],
                $maintenanceDispatcherAvailable,
                metrics: $metrics,
            ),
        ];
        if (
            (bool) $configuration['features']['document_preview']
            && ((bool) $configuration['document_preview']['pdf'] || (bool) $configuration['document_preview']['office'])
        ) {
            $checks[] = new DocumentPreviewHealthCheck(
                (bool) $configuration['document_preview']['pdf'],
                (bool) $configuration['document_preview']['office'],
                (string) $configuration['document_preview']['office_binary'],
            );
        }

        return new HealthManager($checks);
    }
}
