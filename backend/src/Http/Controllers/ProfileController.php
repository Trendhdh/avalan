<?php

declare(strict_types=1);

namespace Avalan\SmartPay\Http\Controllers;

use Avalan\SmartPay\Repositories\DemoDataStore;
use Avalan\SmartPay\RiskEngine\RiskEngine;
use Avalan\SmartPay\Services\BalanceEngine;
use Avalan\SmartPay\Services\LiabilityEngine;
use Avalan\SmartPay\Services\ScoreEngine;
use Avalan\SmartPay\Utilities\Money;
use DateTimeImmutable;

/**
 * ProfileController (demo)
 *
 * GET /api/demo/profile — user profile + the Financial Score (Rank +
 * Rating, see ScoreEngine) + the latest Risk snapshot (see RiskEngine),
 * for profile.php and financial-profile.php. Recomputes risk the same
 * way SmartPayController::compute() does, since ScoreEngine's
 * "financial_health" component depends on a fresh risk read.
 */
final class ProfileController
{
    public function __construct(
        private readonly DemoDataStore $store,
        private readonly BalanceEngine $balanceEngine,
        private readonly LiabilityEngine $liabilityEngine,
        private readonly RiskEngine $riskEngine,
        private readonly ScoreEngine $scoreEngine
    ) {
    }

    public function show(int $userId = 1): array
    {
        $currency = 'UZS';
        $reference = new DateTimeImmutable('today');

        $balance = $this->balanceEngine->getTotalBalance($userId, $currency);
        $liabilities = $this->liabilityEngine->collect($userId, $currency, $reference);
        $available = $balance['total_balance']->subtractClamped($liabilities['total_reserved']);
        $avgDailyMandatorySpend = $liabilities['total_mandatory_reserved']->toMajorFloat() / 30;

        $risk = $this->riskEngine->evaluate(
            userId: $userId,
            totalBalance: $balance['total_balance'],
            totalDebtObligations: $liabilities['total_mandatory_reserved'],
            totalReserved: $liabilities['total_reserved'],
            availableMoney: $available,
            avgDailyMandatorySpendMajor: $avgDailyMandatorySpend,
            balanceDegraded: $balance['degraded'],
            incomePredictionConfidence: 80.0,
            expectedIncomeWithinWindow: Money::fromMinorUnits($this->store->monthlyIncomeMinor(), $currency),
            monthlyIncome: Money::fromMinorUnits($this->store->monthlyIncomeMinor(), $currency)
        );

        $score = $this->scoreEngine->computeAndLog($userId, $risk);

        return [
            'user'  => $this->store->user(),
            'score' => $score,
            'risk'  => $risk,
        ];
    }
}
