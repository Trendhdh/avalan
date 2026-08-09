<?php

declare(strict_types=1);

namespace Avalan\SmartPay\Repositories;

/**
 * Narrow interface RiskEngine depends on. Ported unchanged from the
 * production backend. RiskEngine only ever calls logRisk() on a
 * repository, so that is the entire surface exposed here.
 */
interface RiskRepositoryForRiskEngine
{
    public function logRisk(
        int $userId,
        int $totalBalanceMinor,
        int $reservedMinor,
        int $availableMinor,
        float $debtRatio,
        float $reserveRatio,
        float $liquidityRatio,
        float $emergencyDays,
        float $stressScore,
        float $confidenceScore,
        float $doubtScore,
        bool $crisisMode
    ): int;
}
