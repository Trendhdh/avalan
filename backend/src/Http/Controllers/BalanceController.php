<?php

declare(strict_types=1);

namespace Avalan\SmartPay\Http\Controllers;

use Avalan\SmartPay\Repositories\DemoDataStore;
use Avalan\SmartPay\Services\BalanceEngine;

/**
 * BalanceController (demo)
 *
 * GET /api/demo/balance — per-card + cash breakdown for balance.php.
 * Production's real BalanceController (see that file) additionally
 * exposes the internal, non-withdrawable "Avalan Balans" credit wallet
 * (top-up, send-to-friend, spend-on-subscription) — out of scope for a
 * public architecture demo since it involves real payment webhooks.
 */
final class BalanceController
{
    public function __construct(
        private readonly DemoDataStore $store,
        private readonly BalanceEngine $balanceEngine
    ) {
    }

    public function status(int $userId = 1): array
    {
        $balance = $this->balanceEngine->getTotalBalance($userId);

        $cards = array_map(static fn (array $c) => [
            'id'      => $c['id'],
            'bank'    => $c['bank'],
            'last4'   => $c['last4'],
            'balance' => ['minor_units' => $c['balance_minor'], 'amount' => number_format($c['balance_minor'] / 100, 2, '.', ''), 'currency' => 'UZS'],
        ], $this->store->cards());

        return [
            'total_balance' => $balance['total_balance']->toArray(),
            'card_balance'  => $balance['card_balance']->toArray(),
            'cash_balance'  => $balance['cash_balance']->toArray(),
            'cards'         => $cards,
        ];
    }
}
