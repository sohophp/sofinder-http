<?php

declare(strict_types=1);

namespace SohoPHP\SoFinder\Http\Action;

use SohoPHP\SoFinder\Health\HealthManager;
use SohoPHP\SoFinder\Http\EndpointActionInterface;
use SohoPHP\SoFinder\Http\EndpointResult;
use SohoPHP\SoFinder\Value\RequestContext;

final readonly class HealthAction implements EndpointActionInterface
{
    public function __construct(private HealthManager $health)
    {
    }

    public function endpoint(): string
    {
        return 'sofinder_health';
    }

    public function execute(RequestContext $context = new RequestContext(), array $input = []): EndpointResult
    {
        $report = $this->health->report();
        $available = $report['status'] !== 'down';

        return new EndpointResult(
            ['success' => $available, 'data' => $report],
            $available ? 200 : 503,
        );
    }
}
