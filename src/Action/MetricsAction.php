<?php

declare(strict_types=1);

namespace SohoPHP\SoFinder\Http\Action;

use SohoPHP\SoFinder\Contract\MetricsStoreInterface;
use SohoPHP\SoFinder\Health\HealthManager;
use SohoPHP\SoFinder\Http\EndpointActionInterface;
use SohoPHP\SoFinder\Http\StreamEndpointResult;
use SohoPHP\SoFinder\Value\RequestContext;

final readonly class MetricsAction implements EndpointActionInterface
{
    public function __construct(private MetricsStoreInterface $metrics, private HealthManager $health)
    {
    }

    public function endpoint(): string
    {
        return 'sofinder_metrics';
    }

    public function execute(RequestContext $context = new RequestContext(), array $input = []): StreamEndpointResult
    {
        $lines = [
            '# TYPE sofinder_ready gauge',
            'sofinder_ready ' . ($this->health->report()['status'] === 'down' ? '0' : '1'),
        ];
        $types = [];
        foreach ($this->metrics->snapshot() as $metric) {
            if (!isset($types[$metric['name']])) {
                $kind = in_array($metric['name'], ['sofinder_queue_backlog', 'sofinder_queue_failed'], true) ? 'gauge' : 'counter';
                $lines[] = '# TYPE ' . $metric['name'] . ' ' . $kind;
                $types[$metric['name']] = true;
            }
            $labels = [];
            foreach ($metric['labels'] as $name => $value) {
                $labels[] = $name . '="' . addcslashes($value, "\\\n\r\"") . '"';
            }
            $lines[] = $metric['name'] . ($labels === [] ? '' : '{' . implode(',', $labels) . '}') . ' ' . $metric['value'];
        }

        $stream = fopen('php://temp', 'w+b');
        if ($stream === false) {
            throw new \RuntimeException('Unable to create the metrics response stream.');
        }
        fwrite($stream, implode("\n", $lines) . "\n");
        rewind($stream);

        return new StreamEndpointResult($stream, headers: [
            'Content-Type' => 'text/plain; version=0.0.4; charset=UTF-8',
            'Cache-Control' => 'no-store, private',
        ]);
    }
}
