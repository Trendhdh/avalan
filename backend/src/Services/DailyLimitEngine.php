<?php

declare(strict_types=1);

namespace Avalan\SmartPay\Services;

use Avalan\SmartPay\Utilities\Money;
use Avalan\SmartPay\Utilities\SafeMath;

/**
 * DailyLimitEngine
 *
 * Daily Limit = Remaining Available Balance / Days Until Next Income.
 *
 * Deliberately NOT a fixed 30-day divisor: if the predicted next income
 * is 8 days away, divide by 8; if unavailable, fall back to 30.
 * SafeMath::safeDivisor is a final defensive floor, so this class can
 * never divide by zero. Ported unchanged from the production backend.
 */
final class DailyLimitEngine
{
    /**
     * @return array{daily_limit: Money, days_used_for_division: int}
     */
    public function computeDailyLimit(Money $remainingAvailable, int $daysUntilIncome): array
    {
        $safeDays = SafeMath::safeDivisor($daysUntilIncome, 30);
        $dailyLimit = $remainingAvailable->clampNonNegative()->divideBy($safeDays);

        return [
            'daily_limit'            => $dailyLimit,
            'days_used_for_division' => $safeDays,
        ];
    }
}
