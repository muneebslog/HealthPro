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
use Mike42\Escpos\Printer as EscposPrinter;

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

    public function printTest(?string $printerPort = null): void
    {
        $port = $printerPort ?? config('printing.default_printer_port', 'COM8');
        $defaultSize = 1;

        $printer = $this->connectorFactory->printer($port);
        try {
            $printer->setJustification(EscposPrinter::JUSTIFY_CENTER);
            $printer->setTextSize($defaultSize + 2, $defaultSize + 2);
            $printer->setEmphasis(true);
            $printer->text("HEALTH PRO\n");

            $printer->setTextSize($defaultSize, $defaultSize);
            $printer->setEmphasis(false);
            $printer->text('Date: '.now()->format('d M H:i')."   \n");

            $printer->setTextSize($defaultSize + 1, $defaultSize + 1);
            $printer->setEmphasis(true);
            $printer->text("PRINTER TEST\n\n");

            $printer->setTextSize($defaultSize, $defaultSize);
            $printer->setEmphasis(false);
            $printer->text(str_repeat('-', 32)."\n");
            $printer->text("If you see this, the printer works.\n");
            $printer->text(str_repeat('-', 32)."\n");

            $printer->feed(2);
            $printer->cut();
        } finally {
            $printer->close();
        }
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
