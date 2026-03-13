<?php

namespace App\Printing\ReceiptTemplates;

use App\Models\Invoice;

class InvoiceReceiptTemplate extends AbstractReceiptTemplate
{
    public function __construct(
        private readonly Invoice $invoice
    ) {}

    protected function getReceiptType(): string
    {
        return 'Invoice';
    }

    protected function getBodyContent(): string
    {
        $invoice = $this->invoice->loadMissing([
            'visit.queueTokens.queue',
            'visit.patient.family',
            'invoiceServices.servicePrice.service',
            'invoiceServices.servicePrice.doctor',
        ]);

        $out = '';

        $patient = $invoice->visit?->patient;
        $out .= 'Patient: '.($patient?->name ?? '—')."\n";
        $out .= 'MR#: '.($patient?->mr_number ?? '—')."\n";
        $phone = $patient?->family?->phone ?? '';
        if ($phone !== '') {
            $out .= 'Phone: '.$phone."\n";
        }
        $out .= "\n";

        $tokens = $invoice->visit?->queueTokens ?? collect();
        $hasDoc1Service1 = false;

        foreach ($invoice->invoiceServices as $invSvc) {
            $sp = $invSvc->servicePrice;
            if (! $sp) {
                continue;
            }
            $serviceName = $sp->service?->name ?? 'Service';
            $doctorName = $sp->doctor?->name ?? null;
            if ($sp->doctor_id === 1 && $sp->service_id === 1) {
                $hasDoc1Service1 = true;
            }

            if ($doctorName !== null && $doctorName !== '') {
                $out .= $serviceName.' - '.$doctorName."\n";
            } else {
                $out .= $serviceName."\n";
            }

            $token = $tokens->first(function ($t) use ($sp) {
                $q = $t->queue;
                if (! $q) {
                    return false;
                }

                return (int) $q->service_id === (int) $sp->service_id
                    && (int) ($q->doctor_id ?? 0) === (int) ($sp->doctor_id ?? 0);
            });
            $tokenNum = $token ? (string) $token->token_number : '—';
            $out .= '#'.$tokenNum.'    Rs '.number_format($invSvc->final_amount)."\n\n";
        }

        return $out;
    }

    protected function getSmallBodyContent(): string
    {
        $hasDoc1Service1 = $this->invoice->invoiceServices->contains(fn ($invSvc) => (int) ($invSvc->servicePrice?->doctor_id ?? 0) === 1 && (int) ($invSvc->servicePrice?->service_id ?? 0) === 1);

        $out = "bp:                  temp:\n";
        if ($hasDoc1Service1) {
            $out .= "Rx:\n\n\n\n\n\n";
        }

        return $out;
    }
}
