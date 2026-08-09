<?php

declare(strict_types=1);

namespace Avalan\SmartPay\Repositories;

use DateTimeImmutable;

/**
 * Narrow interface LiabilityEngine depends on. Ported unchanged from the
 * production backend, which uses this same interface to keep the engine
 * decoupled from any one storage implementation — the demo's
 * DemoDataStore implements it directly against seeded fixture data
 * instead of MySQL.
 */
interface LiabilityRepositoryForLiabilityEngine
{
    public function getUpcomingLiabilities(int $userId, int $withinDays, DateTimeImmutable $reference): array;

    public function getFutureLookahead(int $userId, int $fromDaysExclusive, int $toDaysInclusive, DateTimeImmutable $reference): array;
}
