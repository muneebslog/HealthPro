<?php

namespace App\Printing\ReceiptTemplates;

use App\Models\Invoice;
use App\Models\Patient;

class InvoiceReceiptTemplate extends AbstractReceiptTemplate
{
    public function __construct(
        private readonly Invoice $invoice
    ) {}

    protected function getReceiptType(): string
    {
        return $this->invoice->isProcedure() ? 'Procedure Invoice' : 'Invoice';
    }

    protected function getBodyContent(): string
    {
        if ($this->invoice->isProcedure()) {
            return $this->getProcedureBodyContent();
        }

        return $this->getVisitBodyContent();
    }

    protected function getProcedureBodyContent(): string
    {
        $invoice = $this->invoice->loadMissing([
            'patient.family',
            'procedureAdmission.operationDoctor',
        ]);

        $patient = $invoice->patient;
        $mrNumber = $patient?->mr_number;
        if ($patient !== null && ($mrNumber === null || $mrNumber === '')) {
            $patient->mr_number = Patient::generateMrNumber();
            $patient->saveQuietly();
            $mrNumber = $patient->mr_number;
        }

        $admission = $invoice->procedureAdmission;
        $out = 'Patient: '.($patient?->name ?? '—')."\n";
        $out .= 'MR#: '.($mrNumber ?? '—')."\n";
        $phone = $patient?->family?->phone ?? '';
        if ($phone !== '') {
            $out .= 'Phone: '.$phone."\n";
        }
        $out .= "\n";

        $out .= 'Package: '.($admission?->package_name ?? '—')."\n";
        $out .= 'Full Price: Rs '.number_format($invoice->total_amount)."\n";
        $out .= 'Advance Paid: Rs '.number_format($invoice->paid_amount)."\n";
        $out .= 'Remaining: Rs '.number_format($invoice->remainingBalance())."\n\n";

        if ($admission?->operationDoctor) {
            $out .= 'Operation Doctor: '.$admission->operationDoctor->name."\n";
        }
        if ($admission?->operation_date) {
            $out .= 'Operation Date: '.$admission->operation_date->format('M j, Y')."\n";
        }
        if ($admission?->room) {
            $out .= 'Room: '.$admission->room."\n";
        }
        if ($admission?->bed) {
            $out .= 'Bed: '.$admission->bed."\n";
        }

        return $out;
    }

    protected function getVisitBodyContent(): string
    {
        $invoice = $this->invoice->loadMissing([
            'visit.queueTokens.queue',
            'visit.patient.family',
            'patient.family',
            'invoiceServices.servicePrice.service',
            'invoiceServices.servicePrice.doctor',
        ]);

        $out = '';

        $patient = $invoice->patient ?? $invoice->visit?->patient;
        $mrNumber = $patient?->mr_number;
        if ($patient !== null && ($mrNumber === null || $mrNumber === '')) {
            $patient->mr_number = Patient::generateMrNumber();
            $patient->saveQuietly();
            $mrNumber = $patient->mr_number;
        }

        $out .= 'Patient: '.($patient?->name ?? '—')."\n";
        $out .= 'MR#: '.($mrNumber ?? '—')."\n";
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
        if ($this->invoice->isProcedure()) {
            return '';
        }

        $hasDoc1Service1 = $this->invoice->invoiceServices->contains(fn ($invSvc) => (int) ($invSvc->servicePrice?->doctor_id ?? 0) === 1 && (int) ($invSvc->servicePrice?->service_id ?? 0) === 1);

        $out = "bp:                  temp:\n";
        if ($hasDoc1Service1) {
            $out .= "Rx:\n\n\n\n\n\n";
        }

        return $out;
    }
}
