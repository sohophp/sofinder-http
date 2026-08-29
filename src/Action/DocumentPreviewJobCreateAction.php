<?php
declare(strict_types=1);
namespace SohoPHP\SoFinder\Http\Action;
use SohoPHP\SoFinder\Http\DocumentPreviewJobService;
use SohoPHP\SoFinder\Http\EndpointResult;
use SohoPHP\SoFinder\Http\MutationActionInterface;
use SohoPHP\SoFinder\Http\MutationGuard;
use SohoPHP\SoFinder\Value\OperationResult;
use SohoPHP\SoFinder\Value\RequestContext;
final readonly class DocumentPreviewJobCreateAction implements MutationActionInterface
{
    public function __construct(private DocumentPreviewJobService $service, private MutationGuard $guard) {}
    public function endpoint(): string { return 'sofinder_document_preview_job_create'; }
    public function assertAllowed(RequestContext $context, array $input = []): void { $this->guard->assertAllowed($context, $input); }
    public function execute(RequestContext $context = new RequestContext(), array $input = []): EndpointResult
    {
        $this->assertAllowed($context, $input); $job = $this->service->create($input);
        return new EndpointResult(OperationResult::success($job)->jsonSerialize(), in_array($job['status'], ['ready', 'failed', 'expired'], true) ? 200 : 202, (int) $job['retryAfter'] > 0 ? ['Retry-After' => (string) $job['retryAfter']] : []);
    }
}
