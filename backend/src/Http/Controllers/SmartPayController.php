<?php

declare(strict_types=1);

namespace Avalan\SmartPay\Http\Controllers;

use Avalan\SmartPay\Repositories\DemoDataStore;
use Avalan\SmartPay\RiskEngine\RiskEngine;
use Avalan\SmartPay\Services\BalanceEngine;
use Avalan\SmartPay\Services\DailyLimitEngine;
use Avalan\SmartPay\Services\LiabilityEngine;
use Avalan\SmartPay\Services\PaymentAllocationEngine;
use Avalan\SmartPay\Utilities\Money;
use DateTimeImmutable;

/**
 * SmartPayController (demo)
 *
 * GET /api/demo/smartpay/compute
 *
 * This is the same orchestration production's real
 * `GET /api/smartpay/compute` performs (see SmartPayController +
 * SmartPayEngine::compute() there): pull the balance, collect upcoming
 * liabilities, derive the daily spending limit, evaluate risk, and turn
 * all of it into a concrete action plan. Every number in the response
 * comes from the real BalanceEngine / LiabilityEngine / DailyLimitEngine
 * / RiskEngine / PaymentAllocationEngine classes ported into this demo
 * — only the data source (fixture JSON instead of MySQL + live Paylov)
 * and the request/response plumbing are simplified.
 */
final class SmartPayController
{
    public function __construct(
        private readonly DemoDataStore $store,
        private readonly BalanceEngine $balanceEngine,
        private readonly LiabilityEngine $liabilityEngine,
        private readonly DailyLimitEngine $dailyLimitEngine,
        private readonly RiskEngine $riskEngine,
        private readonly PaymentAllocationEngine $allocationEngine
    ) {
    }

    public function compute(int $userId = 1): array
    {
        $reference = new DateTimeImmutable('today');
        $currency = 'UZS';

        $balance = $this->balanceEngine->getTotalBalance($userId, $currency);
        $liabilities = $this->liabilityEngine->collect($userId, $currency, $reference);

        $availableAfterReserves = $balance['total_balance']->subtractClamped($liabilities['total_reserved']);

        $dailyLimit = $this->dailyLimitEngine->computeDailyLimit(
            $availableAfterReserves,
            $this->store->daysUntilNextIncome()
        );

        $avgDailyMandatorySpend = $liabilities['total_mandatory_reserved']->toMajorFloat() / 30;

        $risk = $this->riskEngine->evaluate(
            userId: $userId,
            totalBalance: $balance['total_balance'],
            totalDebtObligations: $liabilities['total_mandatory_reserved'],
            totalReserved: $liabilities['total_reserved'],
            availableMoney: $availableAfterReserves,
            avgDailyMandatorySpendMajor: $avgDailyMandatorySpend,
            balanceDegraded: $balance['degraded'],
            incomePredictionConfidence: 80.0,
            expectedIncomeWithinWindow: Money::fromMinorUnits($this->store->monthlyIncomeMinor(), $currency),
            monthlyIncome: Money::fromMinorUnits($this->store->monthlyIncomeMinor(), $currency)
        );

        $plan = $this->allocationEngine->buildPlan(
            today: $liabilities['today'],
            next30Days: $liabilities['next_30_days'],
            availableAfterReserves: $availableAfterReserves,
            totalReserved: $liabilities['total_reserved'],
            reference: $reference,
            dailyLimit: $dailyLimit['daily_limit'],
            daysUsedForDivision: $dailyLimit['days_used_for_division']
        );

        return [
            'balance'      => [
                'total_balance' => $balance['total_balance']->toArray(),
                'card_balance'  => $balance['card_balance']->toArray(),
                'cash_balance'  => $balance['cash_balance']->toArray(),
            ],
            'reserved'     => $liabilities['total_reserved']->toArray(),
            'available'    => $availableAfterReserves->toArray(),
            'daily_limit'  => [
                'amount'                  => $dailyLimit['daily_limit']->toArray(),
                'days_used_for_division'  => $dailyLimit['days_used_for_division'],
            ],
            'risk'         => $risk,
            'today_actions'     => $plan['today_actions'],
            'payment_plan'      => $plan['payment_plan'],
            'recommended_order' => $plan['recommended_order'],
        ];
    }
}
