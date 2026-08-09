<?php

declare(strict_types=1);

namespace Avalan\SmartPay\Http\Controllers;

use Avalan\SmartPay\Services\DemoAuthService;

/**
 * AuthController (demo)
 *
 * Mirrors production's POST /api/smartpay/auth/login shape (see
 * AuthController + AuthService) without any real credential check —
 * the demo has exactly one fixture user, so "login" just hands back the
 * fixed demo bearer token described in DemoAuthService.
 */
final class AuthController
{
    public function __construct(private readonly DemoAuthService $auth)
    {
    }

    public function login(): array
    {
        return $this->auth->login();
    }
}
