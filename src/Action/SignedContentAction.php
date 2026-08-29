<?php
declare(strict_types=1);
namespace SohoPHP\SoFinder\Http\Action;
use SohoPHP\SoFinder\Http\EndpointActionInterface;
use SohoPHP\SoFinder\Http\EntryStreamResponseBuilder;
use SohoPHP\SoFinder\Http\StreamEndpointResult;
use SohoPHP\SoFinder\Security\SignedUrlManager;
use SohoPHP\SoFinder\Value\RequestContext;
final readonly class SignedContentAction implements EndpointActionInterface
{
    public function __construct(private SignedUrlManager $signedUrls, private EntryStreamResponseBuilder $responses = new EntryStreamResponseBuilder()) {}
    public function endpoint(): string { return 'sofinder_signed_content'; }
    public function execute(RequestContext $context = new RequestContext(), array $input = []): StreamEndpointResult
    {
        $value = $input['token'] ?? $context->attribute('token'); $token = is_scalar($value) || $value instanceof \Stringable ? (string) $value : ''; $opened = $this->signedUrls->open($token);
        return $this->responses->build($context, $opened['resource'], $opened['entry'], $opened['stream'], $opened['disposition'], 'public, max-age=' . max(0, $opened['expiresAt'] - time()), ['Referrer-Policy' => 'no-referrer']);
    }
}
