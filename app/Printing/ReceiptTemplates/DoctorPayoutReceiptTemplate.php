<?php

namespace App\Printing\ReceiptTemplates;

use App\Models\DoctorPayout;

class DoctorPayoutReceiptTemplate extends AbstractReceiptTemplate
{
    public function __construct(
        private readonly DoctorPayout $payout
    ) {}

    public function toEscPosText(): string
    {
        $payout = $this->payout->loadMissing([
            'doctor',
            'ledgerEntries.invoiceService.servicePrice.service',
        ]);

        $out = $this->header('Doctor payout');

        $out .= 'Doctor: '.($payout->doctor?->name ?? '—')."\n";
        $duration = $payout->doctor?->payout_duration;
        $out .= 'Payout duration: '.($duration !== null ? $duration.' days' : '—')."\n";
        $out .= 'Period: '.$payout->period_from->format('M j, Y').' - '.$payout->period_to->format('M j, Y')."\n";

        $count = $payout->ledgerEntries->count();
        $out .= 'Number of transactions: '.$count."\n\n";

        foreach ($payout->ledgerEntries as $entry) {
            $invSvc = $entry->invoiceService;
            $serviceName = $invSvc?->servicePrice?->service?->name ?? 'Service';
            $out .= $serviceName.': Rs '.number_format($entry->share_amount)."\n";
        }

        $out .= "\nTotal share: Rs ".number_format($payout->amount)."\n";
        $out .= "\n".$this->footer();

        return $out;
    }
}
