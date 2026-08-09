<?php

declare(strict_types=1);

namespace Avalan\SmartPay\RiskEngine;

use Avalan\SmartPay\Repositories\RiskRepositoryForRiskEngine;
use Avalan\SmartPay\Utilities\Money;
use Avalan\SmartPay\Utilities\SafeMath;

/**
 * RiskEngine
 *
 * Pure, deterministic mathematics — no AI/LLM involved. Computes the
 * risk metrics that drive Crisis Mode detection and the API's `risk`
 * block:
 *
 *  - Debt Ratio            = total scheduled debt (loans+mandatory) / total balance
 *  - Reserve Ratio         = reserved money / total balance
 *  - Liquidity Ratio       = available money / total balance
 *  - Emergency Days        = available money / avg daily mandatory spend
 *  - Financial Stress Score (0-100, higher = worse)
 *  - Confidence Score (0-100, trust in the numbers behind this assessment)
 *  - Doubt Score = 100 - Confidence Score
 *
 * All ratios are computed on floats derived from Money minor-units
 * (safe, since ratios are dimensionless — only absolute money amounts
 * must stay integer). Every division goes through SafeMath. Ported
 * unchanged from the production backend.
 */
final class RiskEngine
{
    public function __construct(
        private readonly RiskRepositoryForRiskEngine $riskRepo
    ) {
    }

    /**
     * @return array{
     *   debt_ratio: float, reserve_ratio: float, liquidity_ratio: float,
     *   emergency_days: float, stress_score: float, confidence_score: float,
     *   doubt_score: float, crisis_mode: bool, risk_log_id: int,
     *   debt_to_income_ratio: ?float, monthly_income_minor: int,
     *   income_data_available: bool
     * }
     */
    public function evaluate(
        int $userId,
        Money $totalBalance,
        Money $totalDebtObligations,
        Money $totalReserved,
        Money $availableMoney,
        float $avgDailyMandatorySpendMajor,
        bool $balanceDegraded,
        float $incomePredictionConfidence,
        ?Money $expectedIncomeWithinWindow = null,
        ?Money $monthlyIncome = null
    ): array {
        $totalMajor = $totalBalance->toMajorFloat();
        $debtMajor = $totalDebtObligations->toMajorFloat();
        $reservedMajor = $totalReserved->toMajorFloat();
        $availableMajor = $availableMoney->toMajorFloat();

        $debtRatio = SafeMath::clamp(SafeMath::safeDivideFloat($debtMajor, $totalMajor, 0.0), 0.0, 10.0);
        $reserveRatio = SafeMath::clamp(SafeMath::safeDivideFloat($reservedMajor, $totalMajor, 0.0), 0.0, 10.0);
        $liquidityRatio = SafeMath::clamp(SafeMath::safeDivideFloat($availableMajor, $totalMajor, 0.0), 0.0, 10.0);

        $emergencyDays = SafeMath::safeDivideFloat($availableMajor, max(0.01, $avgDailyMandatorySpendMajor), 0.0);
        $emergencyDays = SafeMath::finiteOrFallback($emergencyDays, 0.0);

        $monthlyIncomeMinor = $monthlyIncome?->minorUnits() ?? 0;
        $debtToIncomeRatio = null;
        if ($monthlyIncomeMinor > 0) {
            $debtToIncomeRatio = SafeMath::clamp(
                SafeMath::safeDivideFloat($debtMajor, $monthlyIncome->toMajorFloat(), 0.0),
                0.0,
                5.0
            );
        }

        $stressScore = $this->computeStressScore($debtRatio, $reserveRatio, $liquidityRatio, $emergencyDays, $availableMajor, $debtToIncomeRatio);

        $confidenceScore = $this->computeConfidenceScore($balanceDegraded, $incomePredictionConfidence);
        $doubtScore = SafeMath::clamp(100.0 - $confidenceScore, 0.0, 100.0);

        $netAvailable = $totalBalance->add($expectedIncomeWithinWindow ?? Money::zero($totalBalance->currency()));
        $crisisMode = $totalDebtObligations->isGreaterThan($netAvailable) || $availableMoney->isNegative();

        $riskLogId = $this->riskRepo->logRisk(
            $userId,
            $totalBalance->minorUnits(),
            $totalReserved->minorUnits(),
            $availableMoney->minorUnits(),
            $debtRatio,
            $reserveRatio,
            $liquidityRatio,
            $emergencyDays,
            $stressScore,
            $confidenceScore,
            $doubtScore,
            $crisisMode
        );

        return [
            'debt_ratio'       => SafeMath::round2($debtRatio),
            'reserve_ratio'    => SafeMath::round2($reserveRatio),
            'liquidity_ratio'  => SafeMath::round2($liquidityRatio),
            'emergency_days'   => SafeMath::round2($emergencyDays),
            'stress_score'     => SafeMath::round2($stressScore),
            'confidence_score' => SafeMath::round2($confidenceScore),
            'doubt_score'      => SafeMath::round2($doubtScore),
            'crisis_mode'      => $crisisMode,
            'risk_log_id'      => $riskLogId,
            'debt_to_income_ratio' => $debtToIncomeRatio !== null ? SafeMath::round2($debtToIncomeRatio) : null,
            'monthly_income_minor' => $monthlyIncomeMinor,
            'income_data_available' => $monthlyIncomeMinor > 0,
        ];
    }

    /**
     * Weighted composite, 0 (no stress) - 100 (severe stress). When
     * income is known: 20% debt ratio, 20% debt-to-income ratio, 20%
     * inverse liquidity, 20% inverse emergency days, 20% negative-
     * available-money flag. When income is unknown, the DTI slot folds
     * back into the balance-based debt ratio (35/25/20/20).
     */
    private function computeStressScore(
        float $debtRatio,
        float $reserveRatio,
        float $liquidityRatio,
        float $emergencyDays,
        float $availableMajor,
        ?float $debtToIncomeRatio = null
    ): float {
        $debtComponent = SafeMath::clamp(($debtRatio / 2.0) * 100.0, 0.0, 100.0);
        $liquidityComponent = (100.0 - SafeMath::clamp($liquidityRatio * 100.0, 0.0, 100.0));
        $emergencyComponent = (100.0 - SafeMath::clamp(($emergencyDays / 14.0) * 100.0, 0.0, 100.0));
        $negativeFlagComponent = ($availableMajor < 0 ? 100.0 : 0.0);

        if ($debtToIncomeRatio !== null) {
            $dtiComponent = SafeMath::clamp(($debtToIncomeRatio / 0.6) * 100.0, 0.0, 100.0);
            return SafeMath::clamp(
                $debtComponent * 0.20 + $dtiComponent * 0.20 + $liquidityComponent * 0.20
                    + $emergencyComponent * 0.20 + $negativeFlagComponent * 0.20,
                0.0,
                100.0
            );
        }

        return SafeMath::clamp(
            $debtComponent * 0.35 + $liquidityComponent * 0.25 + $emergencyComponent * 0.20 + $negativeFlagComponent * 0.20,
            0.0,
            100.0
        );
    }

    /**
     * Confidence in this risk assessment itself (not in the user's
     * finances). Degrades when balance data was degraded, or when the
     * income prediction feeding this calculation is low-confidence.
     */
    private function computeConfidenceScore(bool $balanceDegraded, float $incomePredictionConfidence): float
    {
        $base = 100.0;
        if ($balanceDegraded) {
            $base -= 40.0;
        }
        $incomePenalty = (100.0 - SafeMath::clamp($incomePredictionConfidence, 0.0, 100.0)) * 0.30;
        return SafeMath::clamp($base - $incomePenalty, 0.0, 100.0);
    }
}
