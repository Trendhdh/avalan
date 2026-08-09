<?php

declare(strict_types=1);

namespace Avalan\SmartPay\Services;

use Avalan\SmartPay\Config\Config;

/**
 * DemoAuthService
 *
 * Production authentication (see AuthService, TelegramAuthService,
 * src/Utilities/Jwt.php) issues real signed JWTs, checks password
 * hashes, and verifies Telegram's HMAC login payload against a bot
 * token — all backed by a JWT_ACCESS_SECRET that must never leave the
 * server. None of that belongs in a demo with no real user accounts.
 *
 * This class demonstrates the SHAPE of that flow (login endpoint issues
 * a bearer token, every other endpoint requires it) with a single
 * fixed, clearly-labeled demo token — safe to publish since it grants
 * access to nothing but read-only fixture data.
 */
final class DemoAuthService
{
    public const DEMO_TOKEN = 'demo-avalan-token';

    public function login(): array
    {
        return [
            'access_token' => self::DEMO_TOKEN,
            'token_type'   => 'Bearer',
            'expires_in'   => 3600,
            'note'         => 'Demo-only static token — production issues a real signed JWT per user (see AuthService::login()).',
        ];
    }

    public function isAuthorized(?string $bearerHeader): bool
    {
        if ($bearerHeader === null) {
            return false;
        }
        $token = preg_replace('/^Bearer\s+/i', '', trim($bearerHeader));
        return hash_equals(self::DEMO_TOKEN, (string) $token);
    }

    public function appKeyValid(?string $providedKey): bool
    {
        $expected = Config::get('AVALAN_DEMO_APP_KEY', 'avalan-demo-app-key');
        return $providedKey !== null && hash_equals((string) $expected, $providedKey);
    }
}
