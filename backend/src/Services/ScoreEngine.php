<?php

declare(strict_types=1);

namespace Avalan\SmartPay\Services;

use Avalan\SmartPay\Repositories\DemoDataStore;
use Avalan\SmartPay\Utilities\SafeMath;

/**
 * ScoreEngine (demo)
 *
 * Produces the user-facing Rank (E, D, C, B, A, S) and Rating (0-1000).
 * The score blends four weighted components, each scored 0-100 first,
 * then blended — the exact same weighting and per-component formulas as
 * the production engine:
 *
 *   35% Income Stability   — confidence-weighted blend of income sources
 *   30% Financial Health   — inverse of the latest risk snapshot
 *   20% Payment Discipline — trailing on-time vs late vs overdue ratio
 *   15% Resilience         — how often the user has fallen into Crisis
 *                            Mode / high stress over recent history
 *
 * Production reads these four inputs from five different MySQL
 * repositories plus a daily-recompute rate gate and an append-only
 * score_snapshots audit table. This demo keeps the real math but reads
 * from the single seeded DemoDataStore and never persists anything —
 * every call recomputes fresh, there is no "already computed today"
 * short-circuit to demonstrate.
 */
final class ScoreEngine
{
    public function __construct(private readonly DemoDataStore $store)
    {
    }

    /**
     * @param array{stress_score:float,debt_ratio:float,liquidity_ratio:float,confidence_score:float}|null $latestRisk
     * @return array{
     *   rating:int, rank:string, rank_label:string,
     *   components:array{income_stability:float,financial_health:float,payment_discipline:float,resilience:float},
     *   data_confidence:float
     * }
     */
    public function computeAndLog(int $userId, ?array $latestRisk = null): array
    {
        $incomeStability = $this->scoreIncomeStability();
        $financialHealth = $this->scoreFinancialHealth($latestRisk);
        $paymentDiscipline = $this->scorePaymentDiscipline();
        $resilience = $this->scoreResilience();

        $rating = $this->blendToRating(
            $incomeStability['score'],
            $financialHealth['score'],
            $paymentDiscipline['score'],
            $resilience['score']
        );

        $rank = $this->ratingToRank($rating);

        $dataConfidence = SafeMath::clamp(SafeMath::round2(
            ($incomeStability['confidence'] * 0.35)
            + ($financialHealth['confidence'] * 0.30)
            + ($paymentDiscipline['confidence'] * 0.20)
            + ($resilience['confidence'] * 0.15)
        ), 0.0, 100.0);

        return [
            'rating'          => $rating,
            'rank'            => $rank['code'],
            'rank_label'      => $rank['label'],
            'components'      => [
                'income_stability'   => $incomeStability['score'],
                'financial_health'   => $financialHealth['score'],
                'payment_discipline' => $paymentDiscipline['score'],
                'resilience'         => $resilience['score'],
            ],
            'data_confidence' => $dataConfidence,
        ];
    }

    public static function rankLabelFor(string $rankCode): string
    {
        return match ($rankCode) {
            'S' => 'Ustuvor',
            'A' => 'Ishonchli',
            'B' => 'Yaxshi',
            'C' => "O'rtacha",
            'D' => 'Beqaror',
            default => "Boshlang'ich",
        };
    }

    /** @return array{score:float, confidence:float, sources:int} */
    private function scoreIncomeStability(): array
    {
        $sources = array_merge($this->store->incomeFingerprints(), $this->store->manualIncomePatterns());

        $weightedSum = 0.0;
        $weightTotal = 0.0;
        $sourceCount = 0;

        foreach ($sources as $s) {
            $confidence = (float) $s['confidence_score'];
            $weightedSum += $confidence * $confidence; // square weighting: reward strong patterns more than weak ones
            $weightTotal += $confidence;
            $sourceCount++;
        }

        if ($sourceCount === 0 || $weightTotal <= 0.0) {
            return ['score' => 0.0, 'confidence' => 0.0, 'sources' => 0];
        }

        $score = SafeMath::clamp(SafeMath::round2($weightedSum / $weightTotal), 0.0, 100.0);

        $diversificationBonus = min(10.0, ($sourceCount - 1) * 4.0);
        $score = SafeMath::clamp(SafeMath::round2($score + $diversificationBonus), 0.0, 100.0);

        $confidence = SafeMath::clamp(min(100.0, $sourceCount * 25.0), 0.0, 100.0);

        return ['score' => $score, 'confidence' => $confidence, 'sources' => $sourceCount];
    }

    /**
     * @param array{stress_score:float,debt_ratio:float,liquidity_ratio:float,confidence_score:float}|null $latestRisk
     * @return array{score:float, confidence:float}
     */
    private function scoreFinancialHealth(?array $latestRisk): array
    {
        if ($latestRisk === null) {
            return ['score' => 0.0, 'confidence' => 0.0];
        }

        $stressScore = (float) $latestRisk['stress_score'];
        $debtRatio = (float) $latestRisk['debt_ratio'];
        $liquidityRatio = (float) $latestRisk['liquidity_ratio'];
        $riskConfidence = (float) $latestRisk['confidence_score'];

        $inverseStress = 100.0 - SafeMath::clamp($stressScore, 0.0, 100.0);
        $inverseDebt = 100.0 - SafeMath::clamp(($debtRatio / 2.0) * 100.0, 0.0, 100.0);
        $liquidityComponent = SafeMath::clamp($liquidityRatio * 100.0, 0.0, 100.0);

        $score = SafeMath::clamp(SafeMath::round2(
            ($inverseStress * 0.5) + ($inverseDebt * 0.30) + ($liquidityComponent * 0.20)
        ), 0.0, 100.0);

        return ['score' => $score, 'confidence' => SafeMath::clamp($riskConfidence, 0.0, 100.0)];
    }

    /** @return array{score:float, confidence:float, on_time:int, late:int, overdue_now:int} */
    private function scorePaymentDiscipline(): array
    {
        $stats = $this->store->paymentDisciplineStats();
        $settled = $stats['on_time'] + $stats['late'];

        if ($stats['total'] === 0) {
            return ['score' => 50.0, 'confidence' => 0.0, 'on_time' => 0, 'late' => 0, 'overdue_now' => 0];
        }

        $onTimeRatio = $settled > 0 ? $stats['on_time'] / $settled : 0.0;
        $overduePenalty = min(40.0, $stats['overdue_now'] * 8.0);

        $score = SafeMath::clamp(SafeMath::round2(($onTimeRatio * 100.0) - $overduePenalty), 0.0, 100.0);
        $confidence = SafeMath::clamp(min(100.0, $stats['total'] * 10.0), 0.0, 100.0);

        return [
            'score'       => $score,
            'confidence'  => $confidence,
            'on_time'     => $stats['on_time'],
            'late'        => $stats['late'],
            'overdue_now' => $stats['overdue_now'],
        ];
    }

    /** @return array{score:float, confidence:float, crisis_rate:float, samples:int} */
    private function scoreResilience(): array
    {
        $history = $this->store->riskHistory(30);

        if (empty($history)) {
            return ['score' => 50.0, 'confidence' => 0.0, 'crisis_rate' => 0.0, 'samples' => 0];
        }

        $crisisCount = 0;
        $stressSum = 0.0;
        foreach ($history as $row) {
            if ((int) $row['crisis_mode'] === 1) {
                $crisisCount++;
            }
            $stressSum += (float) $row['stress_score'];
        }

        $samples = count($history);
        $crisisRate = $crisisCount / $samples;
        $avgStress = $stressSum / $samples;

        $score = SafeMath::clamp(SafeMath::round2(
            ((1.0 - $crisisRate) * 100.0 * 0.6) + ((100.0 - SafeMath::clamp($avgStress, 0.0, 100.0)) * 0.4)
        ), 0.0, 100.0);

        $confidence = SafeMath::clamp(min(100.0, $samples * (100.0 / 30.0)), 0.0, 100.0);

        return ['score' => $score, 'confidence' => $confidence, 'crisis_rate' => SafeMath::round2($crisisRate * 100.0), 'samples' => $samples];
    }

    private function blendToRating(float $incomeStability, float $financialHealth, float $paymentDiscipline, float $resilience): int
    {
        $composite = ($incomeStability * 0.35)
            + ($financialHealth * 0.30)
            + ($paymentDiscipline * 0.20)
            + ($resilience * 0.15);

        $composite = SafeMath::clamp($composite, 0.0, 100.0);

        return (int) round($composite * 10.0); // 0-100 composite -> 0-1000 rating
    }

    /** @return array{code:string, label:string} */
    private function ratingToRank(int $rating): array
    {
        $code = match (true) {
            $rating >= 900 => 'S',
            $rating >= 750 => 'A',
            $rating >= 600 => 'B',
            $rating >= 400 => 'C',
            $rating >= 200 => 'D',
            default        => 'E',
        };
        return ['code' => $code, 'label' => self::rankLabelFor($code)];
    }
}
