<?php

declare(strict_types=1);

namespace SohoPHP\SoFinder\Http\Action;

use SohoPHP\SoFinder\Contract\EndpointUrlGeneratorInterface;
use SohoPHP\SoFinder\Exception\SoFinderException;
use SohoPHP\SoFinder\Http\EndpointActionInterface;
use SohoPHP\SoFinder\Http\EndpointResult;
use SohoPHP\SoFinder\Security\SignedUrlManager;
use SohoPHP\SoFinder\Value\RequestContext;

final readonly class SignedUrlIssueAction implements EndpointActionInterface
{
    public function __construct(private SignedUrlManager $signedUrls, private EndpointUrlGeneratorInterface $urls) {}
    public function endpoint(): string { return 'sofinder_api_signed_url'; }
    public function execute(RequestContext $context = new RequestContext(), array $input = []): EndpointResult
    {
        $ttl = $context->query('ttl');
        if ($ttl !== null && (!is_string($ttl) || preg_match('/^\d+$/D', $ttl) !== 1)) {
            throw new SoFinderException('The signed URL lifetime must be an integer.', 'signed_url_ttl_invalid', 422);
        }
        $issued = $this->signedUrls->issue($this->string($context->query('resource'), 'Files'), $this->string($context->query('path')), is_string($ttl) ? (int) $ttl : null, $this->string($context->query('disposition'), 'attachment'));
        return new EndpointResult(['success' => true, 'data' => ['url' => $this->urls->generate('sofinder_signed_content', ['token' => $issued['token']], true), 'expiresAt' => $issued['expiresAt']]]);
    }
    private function string(mixed $value, string $default = ''): string { return is_scalar($value) || $value instanceof \Stringable ? (string) $value : $default; }
}
