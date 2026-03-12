<?php

namespace App\Printing\ReceiptTemplates;

use App\Models\Shift;

class ShiftCloseReceiptTemplate extends AbstractReceiptTemplate
{
    public function __construct(
        private readonly Shift $shift
    ) {}

    protected function getReceiptType(): string
    {
        return 'End of shift';
    }

    protected function getBodyContent(): string
    {
        $shift = $this->shift->loadMissing(['openedBy', 'expenses', 'doctorPayouts']);

        $out = 'Shift opened by: '.($shift->openedBy?->name ?? '—')."\n";
        $out .= 'Opened at: '.$shift->opened_at->format('M j, Y h:i A')."\n";
        $out .= 'Opening balance: '.number_format((float) $shift->opening_cash, 2)."\n";

        $totalInvoices = $shift->invoices()->sum('total_amount');
        $out .= 'Total invoices: '.number_format($totalInvoices)."\n";

        $totalExpenses = $shift->expenses->sum('amount');
        $out .= 'Expenses: '.number_format($totalExpenses, 2)."\n";

        $totalPayouts = $shift->doctorPayouts->sum('amount');
        $out .= 'Doctor payout: '.number_format($totalPayouts)."\n";
        $out .= "______________\n";

        $expectedCash = $shift->expected_cash ?? (
            (float) $shift->opening_cash + $totalInvoices - $totalExpenses - $totalPayouts
        );
        $out .= 'Expected payment in cash: '.number_format($expectedCash, 2)."\n";

        if ($shift->cash_in_hand !== null) {
            $out .= 'Cash in hand: '.number_format((float) $shift->cash_in_hand, 2)."\n";
        }

        return "\n".$out;
    }
}
