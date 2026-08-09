<?php

declare(strict_types=1);

namespace Avalan\SmartPay\DTO;

use Avalan\SmartPay\Utilities\Money;
use DateTimeImmutable;

/**
 * LiabilityItem
 *
 * A single upcoming obligation, unified across loans and scheduled
 * payments. LiabilityEngine returns a list of these; PaymentAllocationEngine
 * consumes them without caring whether the item originated from a loan
 * or a utility bill. Ported unchanged from the production backend.
 */
final class LiabilityItem
{
    public function __construct(
        public readonly string $sourceType,   // loan_installment | payment_schedule
        public readonly int $sourceId,
        public readonly string $category,      // tax, utility, internet, medical, loan, ...
        public readonly string $label,
        public readonly Money $amount,
        public readonly DateTimeImmutable $dueDate,
        public readonly int $priorityClass,
        public readonly bool $isMandatory,
        public readonly ?string $lenderName = null,
        public readonly string $iconType = 'icon',
        public readonly ?string $iconValue = null
    ) {
    }

    public function daysFromNow(DateTimeImmutable $reference): int
    {
        $diff = $reference->setTime(0, 0)->diff($this->dueDate->setTime(0, 0));
        return (int) $diff->format('%r%a');
    }

    public function isDueWithin(int $days, DateTimeImmutable $reference): bool
    {
        $d = $this->daysFromNow($reference);
        return $d >= 0 && $d <= $days;
    }

    public function isOverdue(DateTimeImmutable $reference): bool
    {
        return $this->daysFromNow($reference) < 0;
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'source_type'    => $this->sourceType,
            'source_id'      => $this->sourceId,
            'category'       => $this->category,
            'label'          => $this->label,
            'amount'         => $this->amount->toArray(),
            'due_date'       => $this->dueDate->format('Y-m-d'),
            'priority_class' => $this->priorityClass,
            'is_mandatory'   => $this->isMandatory,
            'lender_name'    => $this->lenderName,
            'icon_type'      => $this->iconType,
            'icon_value'     => $this->iconValue,
        ];
    }
}
