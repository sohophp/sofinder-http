<?php

declare(strict_types=1);

namespace SohoPHP\SoFinder\Http;

/** Canonical security headers shared by every HTTP host bridge. */
final class SecurityHeaders
{
    public const CONTENT_SECURITY_POLICY = "default-src 'none'; script-src 'self'; style-src 'self'; img-src 'self' data: blob: http: https:; connect-src 'self'; frame-src 'self'; frame-ancestors 'self'; base-uri 'none'; form-action 'self'";

    /** @return array<string,string> */
    public static function defaults(): array
    {
        return [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'X-SoFinder-API-Version' => '1.0',
            'Referrer-Policy' => 'no-referrer',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=()',
            'Cross-Origin-Resource-Policy' => 'same-origin',
            'Content-Security-Policy' => self::CONTENT_SECURITY_POLICY,
        ];
    }
}
