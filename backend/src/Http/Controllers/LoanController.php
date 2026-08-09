<?php

declare(strict_types=1);

namespace Avalan\SmartPay\Http\Controllers;

use Avalan\SmartPay\Repositories\DemoDataStore;

/**
 * LoanController (demo)
 *
 * GET /api/demo/loans — active loans + their upcoming installments, for
 * loans.php. Production's real LoanController also handles create /
 * extra-payment / close / amortization-simulation against MySQL (see
 * LoanRepository's amortization math) — this demo exposes the
 * read-only shape a reviewer needs to see the liability/loan data
 * model, without the write endpoints that would need a real database.
 */
final class LoanController
{
    public function __construct(private readonly DemoDataStore $store)
    {
    }

    public function list(): array
    {
        $loans = array_map(function (array $loan) {
            $progressPct = $loan['term_months'] > 0
                ? round(($loan['months_paid'] / $loan['term_months']) * 100, 1)
                : 0.0;
            return [
                'id'                    => $loan['id'],
                'lender_name'           => $loan['lender_name'],
                'principal'             => $this->money($loan['principal_minor']),
                'remaining'             => $this->money($loan['remaining_minor']),
                'monthly_payment'       => $this->money($loan['monthly_payment_minor']),
                'interest_rate'         => $loan['interest_rate'],
                'term_months'           => $loan['term_months'],
                'months_paid'           => $loan['months_paid'],
                'progress_pct'          => $progressPct,
                'status'                => $loan['status'],
            ];
        }, $this->store->loans());

        return ['loans' => $loans];
    }

    /** @return array{minor_units:int, amount:string, currency:string} */
    private function money(int $minorUnits): array
    {
        return [
            'minor_units' => $minorUnits,
            'amount'      => number_format($minorUnits / 100, 2, '.', ''),
            'currency'    => 'UZS',
        ];
    }
}
