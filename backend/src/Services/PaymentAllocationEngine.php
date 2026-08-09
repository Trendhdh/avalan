<?php

declare(strict_types=1);

namespace Avalan\SmartPay\Services;

use Avalan\SmartPay\DTO\LiabilityItem;
use Avalan\SmartPay\Utilities\Money;
use DateTimeImmutable;

/**
 * PaymentAllocationEngine (the "Smart Payment Engine")
 *
 * Turns the liability list + available balance into a concrete action
 * plan: what to pay today, what to reserve for upcoming due dates, and
 * what is safe to spend right now.
 *
 * Rule enforced here: "Never allow spending reserved money." Money
 * reserved for a future mandatory liability is subtracted from
 * available balance before any "safe to spend" figure is computed — by
 * the time this engine runs, LiabilityEngine has already carved out
 * reserves; this engine only narrates them into an actionable, ordered
 * plan. Ported unchanged from the production backend — this class has
 * no I/O of its own at all.
 */
final class PaymentAllocationEngine
{
    /**
     * @param array<int,LiabilityItem> $today
     * @param array<int,LiabilityItem> $next30Days Full 30-day liability list, ascending due date
     * @return array{
     *   today_actions: array<int,array<string,mixed>>,
     *   payment_plan: array<int,array<string,mixed>>,
     *   recommended_order: array<int,array<string,mixed>>,
     *   safe_to_spend_today: Money,
     *   unsafe_to_spend: Money
     * }
     */
    public function buildPlan(
        array $today,
        array $next30Days,
        Money $availableAfterReserves,
        Money $totalReserved,
        DateTimeImmutable $reference,
        ?Money $dailyLimit = null,
        int $daysUsedForDivision = 1
    ): array {
        $todayActions = [];
        foreach ($today as $item) {
            $todayActions[] = [
                'action'      => 'pay_today',
                'label'       => $item->label,
                'category'    => $item->category,
                'amount'      => $item->amount->toArray(),
                'due_date'    => $item->dueDate->format('Y-m-d'),
                'reason'      => $item->isOverdue($reference)
                    ? "Bu to'lov muddati o'tib ketgan — imkon qadar tezroq amalga oshiring."
                    : "Bu to'lov bugun kerak va allaqachon zaxiraga ajratilgan.",
                'source_type' => $item->sourceType,
                'source_id'   => $item->sourceId,
                'icon_type'   => $item->iconType,
                'icon_value'  => $item->iconValue,
            ];
        }

        $pacedDailyLimit = ($dailyLimit ?? $availableAfterReserves)->clampNonNegative();
        $todayActions[] = $pacedDailyLimit->isZero()
            ? [
                'action'    => 'no_safe_spend_today',
                'label'     => "Bugungi xavfsiz limit",
                'category'  => 'balance_allocation',
                'amount'    => $pacedDailyLimit->toArray(),
                'due_date'  => $reference->format('Y-m-d'),
                'reason'    => "Mavjud mablag'ingizning barchasi kelayotgan majburiy to'lovlar uchun allaqachon zaxiraga ajratilgan — bugun erkin sarflash uchun pul yo'q.",
                'source_type' => 'balance_allocation',
                'source_id'   => null,
            ]
            : [
                'action'    => 'allocate_from_balance',
                'label'     => "Bugungi xavfsiz limit",
                'category'  => 'balance_allocation',
                'amount'    => $pacedDailyLimit->toArray(),
                'due_date'  => $reference->format('Y-m-d'),
                'reason'    => $daysUsedForDivision > 1
                    ? "Mavjud mablag'ingiz keyingi {$daysUsedForDivision} kunga taqsimlangan holda hisoblangan — shu summani sarflasangiz ham ertangi va keyingi kunlar uchun pul qoladi."
                    : "Mavjud mablag'ingizga ko'ra bugun xavfsiz sarflashingiz mumkin bo'lgan miqdor.",
                'source_type' => 'balance_allocation',
                'source_id'   => null,
            ];

        $paymentPlan = [];
        $recommendedOrder = [];
        $orderIndex = 1;

        foreach ($next30Days as $item) {
            $daysAway = $item->daysFromNow($reference);
            $status = match (true) {
                $daysAway < 0  => 'overdue',
                $daysAway === 0 => 'due_today',
                $daysAway <= 7  => 'reserve_soon',
                default         => 'reserved',
            };

            $paymentPlan[] = [
                'label'       => $item->label,
                'category'    => $item->category,
                'amount'      => $item->amount->toArray(),
                'due_date'    => $item->dueDate->format('Y-m-d'),
                'days_away'   => $daysAway,
                'status'      => $status,
                'mandatory'   => $item->isMandatory,
                'lender_name' => $item->lenderName,
                'source_type' => $item->sourceType,
                'source_id'   => $item->sourceId,
                'icon_type'   => $item->iconType,
                'icon_value'  => $item->iconValue,
            ];

            $recommendedOrder[] = [
                'order'     => $orderIndex++,
                'label'     => $item->label,
                'due_date'  => $item->dueDate->format('Y-m-d'),
                'amount'    => $item->amount->toArray(),
                'action'    => $daysAway <= 0 ? 'pay_now' : 'reserve',
            ];
        }

        return [
            'today_actions'       => $todayActions,
            'payment_plan'        => $paymentPlan,
            'recommended_order'   => $recommendedOrder,
            'safe_to_spend_today' => $availableAfterReserves->clampNonNegative(),
            'unsafe_to_spend'     => $totalReserved,
        ];
    }
}
