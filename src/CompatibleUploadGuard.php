<?php

declare(strict_types=1);

namespace SohoPHP\SoFinder\Http;

use SohoPHP\SoFinder\Contract\AuthorizationInterface;
use SohoPHP\SoFinder\Contract\CsrfTokenProviderInterface;
use SohoPHP\SoFinder\Exception\AccessDeniedException;
use SohoPHP\SoFinder\Value\RequestContext;

final readonly class CompatibleUploadGuard
{
    public function __construct(private AuthorizationInterface $authorization, private CsrfTokenProviderInterface $csrf)
    {
    }

    /** @param array<string,mixed> $input */
    public function assertAllowed(RequestContext $context, array $input = []): void
    {
        if (!$this->authorization->isAuthenticated()) {
            throw new AccessDeniedException('Authentication is required.');
        }
        $token = $context->header('X-CSRF-TOKEN');
        if ($token === '' && is_string($input['_token'] ?? null)) {
            $token = $input['_token'];
        }
        if ($token === '' && is_string($context->query('_token'))) {
            $token = $context->query('_token');
        }
        if (!$this->csrf->isValid($context, $token)) {
            throw new AccessDeniedException('The security token is invalid or expired.');
        }
        $expected = strtolower(rtrim($context->schemeAndHost, '/'));
        $origin = strtolower(rtrim($context->header('Origin'), '/'));
        if ($expected !== '' && $origin !== '' && $origin !== $expected) {
            throw new AccessDeniedException('The upload origin is not allowed.');
        }
        $referer = strtolower($context->header('Referer'));
        if ($expected !== '' && $referer !== '' && !str_starts_with($referer, $expected . '/')) {
            throw new AccessDeniedException('The upload referrer is not allowed.');
        }
    }
}
