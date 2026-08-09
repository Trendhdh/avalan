<?php

declare(strict_types=1);

namespace Avalan\SmartPay\Repositories;

use Avalan\SmartPay\DTO\LiabilityItem;
use Avalan\SmartPay\Utilities\Money;
use DateTimeImmutable;

/**
 * DemoDataStore
 *
 * Stands in for the production MySQL repositories. Production Avalan
 * SmartPay reads all of this from real tables (cards, loans,
 * payment_schedule, risk_logs, income_fingerprints, ...) via a dozen+
 * dedicated repository classes talking to MySQL through PDO. None of
 * that — nor any real user data, card token, or database credential —
 * belongs in a public demo.
 *
 * This class loads one fixture user from database/seed_demo.json and
 * implements the exact same narrow repository interfaces the engines
 * depend on (LiabilityRepositoryForLiabilityEngine,
 * RiskRepositoryForRiskEngine), so LiabilityEngine, RiskEngine,
 * PaymentAllocationEngine and DailyLimitEngine below are the REAL
 * production classes, running REAL formulas against this fixture data
 * — only the storage layer is swapped out.
 */
final class DemoDataStore implements LiabilityRepositoryForLiabilityEngine, RiskRepositoryForRiskEngine
{
    /** @var array<string,mixed> */
    private array $data;

    /** @var array<int,array{stress_score:float,crisis_mode:int}> risk logs appended during this request (append-only, in-memory only) */
    private array $riskLogAppends = [];

    public function __construct(?string $seedPath = null)
    {
        $seedPath ??= __DIR__ . '/../../database/seed_demo.json';
        $this->data = json_decode((string) file_get_contents($seedPath), true) ?: [];
    }

    public function user(): array
    {
        return $this->data['user'];
    }

    public function cards(): array
    {
        return $this->data['cards'];
    }

    public function cashBalanceMinor(): int
    {
        return (int) $this->data['cash_balance_minor'];
    }

    public function monthlyIncomeMinor(): int
    {
        return (int) $this->data['monthly_income_minor'];
    }

    public function daysUntilNextIncome(): int
    {
        return (int) $this->data['days_until_next_income'];
    }

    public function loans(): array
    {
        return $this->data['loans'];
    }

    public function incomeFingerprints(): array
    {
        return $this->data['income_fingerprints'];
    }

    public function manualIncomePatterns(): array
    {
        return $this->data['manual_income_patterns'];
    }

    public function paymentDisciplineStats(): array
    {
        return $this->data['payment_discipline'];
    }

    /** @return array<int,array{stress_score:float,crisis_mode:int}> */
    public function riskHistory(int $limit = 30): array
    {
        $stress = $this->data['risk_history_stress_scores'];
        $crisis = $this->data['risk_history_crisis_flags'];
        $history = [];
        foreach ($stress as $i => $s) {
            $history[] = ['stress_score' => (float) $s, 'crisis_mode' => (int) ($crisis[$i] ?? 0)];
        }
        // Most-recent-first, same ordering the production repository returns.
        $history = array_reverse(array_merge($history, $this->riskLogAppends));
        return array_slice($history, 0, $limit);
    }

    // -----------------------------------------------------------------
    // LiabilityRepositoryForLiabilityEngine
    // -----------------------------------------------------------------

    public function getUpcomingLiabilities(int $userId, int $withinDays, DateTimeImmutable $reference): array
    {
        $items = [];
        foreach ($this->data['liabilities'] as $row) {
            $dueInDays = (int) $row['due_in_days'];
            if ($dueInDays > $withinDays) {
                continue;
            }
            $items[] = new LiabilityItem(
                sourceType: $row['source_type'],
                sourceId: (int) $row['source_id'],
                category: $row['category'],
                label: $row['label'],
                amount: Money::fromMinorUnits((int) $row['amount_minor']),
                dueDate: $reference->modify("{$dueInDays} days"),
                priorityClass: $row['is_mandatory'] ? 1 : 3,
                isMandatory: (bool) $row['is_mandatory'],
                lenderName: $row['lender_name'] ?? null
            );
        }
        usort($items, static fn (LiabilityItem $a, LiabilityItem $b) => $a->dueDate <=> $b->dueDate);
        return $items;
    }

    public function getFutureLookahead(int $userId, int $fromDaysExclusive, int $toDaysInclusive, DateTimeImmutable $reference): array
    {
        // Demo fixture only stocks 30 days of obligations — nothing
        // qualifies beyond the standard reservation window.
        return [];
    }

    // -----------------------------------------------------------------
    // RiskRepositoryForRiskEngine
    // -----------------------------------------------------------------

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
    ): int {
        // Production appends an immutable row to risk_logs (MySQL). The
        // demo has nowhere durable to write, so it just returns a
        // synthetic id and keeps the row in memory for the rest of THIS
        // request (e.g. so ScoreEngine's resilience component can see it).
        $this->riskLogAppends[] = ['stress_score' => $stressScore, 'crisis_mode' => $crisisMode ? 1 : 0];
        return 900000 + count($this->riskLogAppends);
    }
}
