<?php

declare(strict_types=1);

namespace SohoPHP\SoFinder\Http;

use SohoPHP\SoFinder\Contract\AuthorizationInterface;
use SohoPHP\SoFinder\Contract\CsrfTokenProviderInterface;
use SohoPHP\SoFinder\Exception\AccessDeniedException;
use SohoPHP\SoFinder\Value\RequestContext;

final class MutationGuard
{
    public function __construct(
        private readonly AuthorizationInterface $authorization,
        private readonly CsrfTokenProviderInterface $csrf,
    ) {
    }

    /** @param array<string, mixed> $input */
    public function assertAllowed(RequestContext $context, array $input = []): void
    {
        if (!$this->authorization->isAuthenticated()) {
            throw new AccessDeniedException('Authentication is required.');
        }
        $token = $context->header('X-CSRF-TOKEN');
        if ($token === '' && is_string($input['_token'] ?? null)) {
            $token = $input['_token'];
        }
        if (!$this->csrf->isValid($context, $token)) {
            throw new AccessDeniedException('The security token is invalid or expired.');
        }
    }
}
