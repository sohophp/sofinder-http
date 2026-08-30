<?php
declare(strict_types=1);
namespace SohoPHP\SoFinder\Http\Action;
use SohoPHP\SoFinder\Http\DocumentPreviewJobService;
use SohoPHP\SoFinder\Http\EndpointActionInterface;
use SohoPHP\SoFinder\Http\EndpointResult;
use SohoPHP\SoFinder\Value\OperationResult;
use SohoPHP\SoFinder\Value\RequestContext;
final class DocumentPreviewJobStatusAction implements EndpointActionInterface
{
    public function __construct(private readonly DocumentPreviewJobService $service) {}
    public function endpoint(): string { return 'sofinder_document_preview_job_status'; }
    public function execute(RequestContext $context = new RequestContext(), array $input = []): EndpointResult
    {
        $value = $input['id'] ?? $context->attribute('id'); $id = is_scalar($value) || $value instanceof \Stringable ? (string) $value : ''; $job = $this->service->status($id);
        return new EndpointResult(OperationResult::success($job)->jsonSerialize(), in_array($job['status'], ['ready', 'failed', 'expired'], true) ? 200 : 202, (int) $job['retryAfter'] > 0 ? ['Retry-After' => (string) $job['retryAfter']] : []);
    }
}
