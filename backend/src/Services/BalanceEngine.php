<?php

declare(strict_types=1);

namespace Avalan\SmartPay\Services;

use Avalan\SmartPay\Repositories\DemoDataStore;
use Avalan\SmartPay\Utilities\Money;

/**
 * BalanceEngine (demo)
 *
 * Total Balance = sum(card balances) + cash balance.
 *
 * The production BalanceEngine sums LIVE card balances fetched from the
 * Paylov open-banking API (with a MySQL cache layer and a parallel-curl
 * fan-out — see docs/ALGORITHMS.md), plus declared cash, plus manual
 * transactions, plus wallets. None of that network/cache machinery
 * belongs in a public demo — there is no real card or real Paylov
 * account behind this fixture. What IS preserved is the actual
 * aggregation rule ("total = every card + cash, nothing double-counted")
 * and the Money-safe arithmetic, applied to the seeded demo user.
 */
final class BalanceEngine
{
    public function __construct(private readonly DemoDataStore $store)
    {
    }

    /**
     * @return array{total_balance: Money, card_balance: Money, cash_balance: Money, degraded: bool}
     */
    public function getTotalBalance(int $userId, string $currency = 'UZS'): array
    {
        $cardTotal = Money::zero($currency);
        foreach ($this->store->cards() as $card) {
            $cardTotal = $cardTotal->add(Money::fromMinorUnits((int) $card['balance_minor'], $currency));
        }

        $cashBalance = Money::fromMinorUnits($this->store->cashBalanceMinor(), $currency);
        $total = $cardTotal->add($cashBalance);

        return [
            'total_balance' => $total,
            'card_balance'  => $cardTotal,
            'cash_balance'  => $cashBalance,
            'degraded'      => false,
        ];
    }
}
