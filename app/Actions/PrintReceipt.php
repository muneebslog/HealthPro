<?php

namespace App\Actions;

use App\Models\DoctorPayout;
use App\Models\Invoice;
use App\Models\ReceiptPrint;
use App\Models\Shift;
use App\Printing\ReceiptPrinterConnectorFactory;
use App\Printing\ReceiptTemplates\DoctorPayoutReceiptTemplate;
use App\Printing\ReceiptTemplates\InvoiceReceiptTemplate;
use App\Printing\ReceiptTemplates\ShiftCloseReceiptTemplate;

class PrintReceipt
{
    public function __construct(
        private readonly ReceiptPrinterConnectorFactory $connectorFactory
    ) {}

    public function forInvoice(Invoice $invoice, ?string $printerPort = null): void
    {
        $port = $printerPort ?? config('printing.default_printer_port', 'COM4');
        $template = new InvoiceReceiptTemplate($invoice);
        $this->print('invoice', $template->toEscPosText(), $port, invoiceId: $invoice->id);
    }

    public function forShiftClose(Shift $shift, ?string $printerPort = null): void
    {
        $port = $printerPort ?? config('printing.default_printer_port', 'COM4');
        $template = new ShiftCloseReceiptTemplate($shift);
        $this->print('shift_close', $template->toEscPosText(), $port, shiftId: $shift->id);
    }

    public function forDoctorPayout(DoctorPayout $payout, ?string $printerPort = null): void
    {
        $port = $printerPort ?? config('printing.default_printer_port', 'COM4');
        $template = new DoctorPayoutReceiptTemplate($payout);
        $this->print('doctor_payout', $template->toEscPosText(), $port, doctorPayoutId: $payout->id);
    }

    /**
     * @param  array{invoice_id?: int, shift_id?: int, doctor_payout_id?: int}  $entityIds
     */
    private function print(string $printType, string $content, string $port, ?int $invoiceId = null, ?int $shiftId = null, ?int $doctorPayoutId = null): void
    {
        $printer = $this->connectorFactory->printer($port);
        try {
            $printer->text($content);
            $printer->cut();
        } finally {
            $printer->close();
        }

        ReceiptPrint::create([
            'print_type' => $printType,
            'invoice_id' => $invoiceId,
            'shift_id' => $shiftId,
            'doctor_payout_id' => $doctorPayoutId,
            'printed_at' => now(),
            'printed_by' => auth()->id(),
            'printer_identifier' => $port,
        ]);
    }
}
