<?php

declare(strict_types=1);

/**
 * public/index.php — demo front controller
 * ---------------------------------------------------------------
 * Same "no framework, dependency-free front controller" approach as
 * production's public/index.php, trimmed to the handful of read-only
 * routes this demo needs:
 *
 *   POST /api/demo/auth/login        -> fixed demo bearer token
 *   GET  /api/demo/balance           -> BalanceController
 *   GET  /api/demo/loans             -> LoanController
 *   GET  /api/demo/smartpay/compute  -> SmartPayController (the Smart
 *                                        Payment Engine pipeline)
 *   GET  /api/demo/profile           -> ProfileController (Financial
 *                                        Score + Risk)
 *   GET  /api/demo/health            -> liveness check, no auth
 *
 * Every route except /health and /auth/login requires:
 *   - an X-Avalan-Demo-Key header (mirrors production's app-key gate —
 *     see docs in .env.example; this demo's value is public on purpose)
 *   - an `Authorization: Bearer <token>` header with the token
 *     /auth/login just handed back (mirrors production's real JWT
 *     bearer check — see DemoAuthService's own docblock for why this
 *     one is a fixed value instead of a signed token)
 */

require __DIR__ . '/../vendor_autoload.php';

use Avalan\SmartPay\Http\Controllers\AuthController;
use Avalan\SmartPay\Http\Controllers\BalanceController;
use Avalan\SmartPay\Http\Controllers\LoanController;
use Avalan\SmartPay\Http\Controllers\ProfileController;
use Avalan\SmartPay\Http\Controllers\SmartPayController;
use Avalan\SmartPay\Repositories\DemoDataStore;
use Avalan\SmartPay\RiskEngine\RiskEngine;
use Avalan\SmartPay\Services\BalanceEngine;
use Avalan\SmartPay\Services\DailyLimitEngine;
use Avalan\SmartPay\Services\DemoAuthService;
use Avalan\SmartPay\Services\LiabilityEngine;
use Avalan\SmartPay\Services\PaymentAllocationEngine;
use Avalan\SmartPay\Services\ScoreEngine;

// ---- CORS (demo-scoped allow-list, same pattern as the frontend's
// api/proxy.php in production — explicit origins only, never "*") ----
$allowedOrigins = ['http://localhost:8080', 'http://127.0.0.1:8080', 'http://localhost:8000', 'http://127.0.0.1:8000'];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowedOrigins, true)) {
    header("Access-Control-Allow-Origin: {$origin}");
    header('Vary: Origin');
    header('Access-Control-Allow-Headers: Authorization, Content-Type, X-Avalan-Demo-Key');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
}
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function respond(int $status, array $body): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$path = rtrim($path, '/');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($path === '/api/demo/health') {
    respond(200, ['status' => 'ok', 'service' => 'avalan-smartpay-demo']);
}

$auth = new DemoAuthService();

if ($path === '/api/demo/auth/login' && $method === 'POST') {
    if (!$auth->appKeyValid($_SERVER['HTTP_X_AVALAN_DEMO_KEY'] ?? null)) {
        respond(403, ['error' => 'invalid_app_key']);
    }
    respond(200, (new AuthController($auth))->login());
}

// Every other route requires both gates.
if (!$auth->appKeyValid($_SERVER['HTTP_X_AVALAN_DEMO_KEY'] ?? null)) {
    respond(403, ['error' => 'invalid_app_key']);
}
if (!$auth->isAuthorized($_SERVER['HTTP_AUTHORIZATION'] ?? null)) {
    respond(401, ['error' => 'unauthorized', 'message' => 'Kirish talab qilinadi']);
}

$store = new DemoDataStore();
$balanceEngine = new BalanceEngine($store);
$liabilityEngine = new LiabilityEngine($store);
$dailyLimitEngine = new DailyLimitEngine();
$riskEngine = new RiskEngine($store);
$scoreEngine = new ScoreEngine($store);
$allocationEngine = new PaymentAllocationEngine();

try {
    switch (true) {
        case $path === '/api/demo/balance' && $method === 'GET':
            respond(200, (new BalanceController($store, $balanceEngine))->status());

        case $path === '/api/demo/loans' && $method === 'GET':
            respond(200, (new LoanController($store))->list());

        case $path === '/api/demo/smartpay/compute' && $method === 'GET':
            $controller = new SmartPayController($store, $balanceEngine, $liabilityEngine, $dailyLimitEngine, $riskEngine, $allocationEngine);
            respond(200, $controller->compute());

        case $path === '/api/demo/profile' && $method === 'GET':
            $controller = new ProfileController($store, $balanceEngine, $liabilityEngine, $riskEngine, $scoreEngine);
            respond(200, $controller->show());

        default:
            respond(404, ['error' => 'not_found', 'path' => $path]);
    }
} catch (Throwable $e) {
    respond(500, ['error' => 'internal_error', 'message' => $e->getMessage()]);
}
