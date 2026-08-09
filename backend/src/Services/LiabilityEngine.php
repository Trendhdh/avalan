<?php

declare(strict_types=1);

namespace Avalan\SmartPay\Services;

use Avalan\SmartPay\DTO\LiabilityItem;
use Avalan\SmartPay\Repositories\LiabilityRepositoryForLiabilityEngine;
use Avalan\SmartPay\Utilities\Money;
use DateTimeImmutable;

/**
 * LiabilityEngine
 *
 * Collects every upcoming mandatory and scheduled obligation over the
 * next 30 days: loan installments, fixed payments, taxes, utilities,
 * subscriptions, and manual entries. Buckets them into today / tomorrow
 * / 7 days / 30 days windows and computes the total "reserved" amount —
 * money that must never be offered up as spendable, regardless of what
 * the current balance looks like.
 *
 * Ported unchanged from the production backend — no database access
 * happens in this class itself, it only calls the injected repository
 * interface, so the real engine runs as-is against the demo's fixture
 * data.
 */
final class LiabilityEngine
{
    /**
     * Fixed rolling horizon for "upcoming obligations" — MUST be a
     * literal day count, never derived from the calendar (e.g. "days
     * left in this month"), otherwise it silently shrinks to 0-3 days
     * near month-end.
     */
    public const OBLIGATION_WINDOW_DAYS = 30;

    public function __construct(
        private readonly LiabilityRepositoryForLiabilityEngine $liabilityRepo
    ) {
    }

    /**
     * @return array{
     *   today: array<int,LiabilityItem>,
     *   tomorrow: array<int,LiabilityItem>,
     *   next_7_days: array<int,LiabilityItem>,
     *   next_30_days: array<int,LiabilityItem>,
     *   total_reserved: Money,
     *   total_mandatory_reserved: Money
     * }
     */
    public function collect(int $userId, string $currency = 'UZS', ?DateTimeImmutable $reference = null): array
    {
        $reference = $reference ?? new DateTimeImmutable('today');

        $all30 = $this->liabilityRepo->getUpcomingLiabilities($userId, self::OBLIGATION_WINDOW_DAYS, $reference);

        $today = array_values(array_filter($all30, fn (LiabilityItem $i) => $i->daysFromNow($reference) === 0 || $i->isOverdue($reference)));
        $tomorrow = array_values(array_filter($all30, fn (LiabilityItem $i) => $i->daysFromNow($reference) === 1));
        $next7 = array_values(array_filter($all30, fn (LiabilityItem $i) => $i->isDueWithin(7, $reference) || $i->isOverdue($reference)));

        $totalReserved = Money::zero($currency);
        $totalMandatory = Money::zero($currency);
        foreach ($all30 as $item) {
            $totalReserved = $totalReserved->add($item->amount);
            if ($item->isMandatory) {
                $totalMandatory = $totalMandatory->add($item->amount);
            }
        }

        return [
            'today'                    => $today,
            'tomorrow'                 => $tomorrow,
            'next_7_days'              => $next7,
            'next_30_days'             => $all30,
            'total_reserved'           => $totalReserved,
            'total_mandatory_reserved' => $totalMandatory,
        ];
    }

    /**
     * Advisory-only lookahead beyond the hard 30-day reservation window.
     *
     * @return array<int,array{item:LiabilityItem, days_until_due:int, suggested_daily_save: Money}>
     */
    public function collectFutureLookahead(int $userId, string $currency = 'UZS', ?DateTimeImmutable $reference = null, int $fromDaysExclusive = self::OBLIGATION_WINDOW_DAYS, int $toDaysInclusive = 90): array
    {
        $reference = $reference ?? new DateTimeImmutable('today');
        $items = $this->liabilityRepo->getFutureLookahead($userId, $fromDaysExclusive, $toDaysInclusive, $reference);

        return array_map(function (LiabilityItem $item) use ($reference) {
            $daysUntilDue = max(1, $item->daysFromNow($reference));
            return [
                'item'                 => $item,
                'days_until_due'       => $daysUntilDue,
                'suggested_daily_save' => Money::fromMinorUnits(
                    (int) ceil($item->amount->minorUnits() / $daysUntilDue),
                    $item->amount->currency()
                ),
            ];
        }, $items);
    }
}
