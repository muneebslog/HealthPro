<?php

namespace App\Printing\ReceiptTemplates;

use Carbon\Carbon;
use Mike42\Escpos\Printer as EscposPrinter;

abstract class AbstractReceiptTemplate
{
    protected const HEADER_SIZE = 2;

    protected const BODY_SIZE = 2;

    public function printTo(EscposPrinter $printer): void
    {
        $printer->setJustification(EscposPrinter::JUSTIFY_CENTER);
        $printer->setTextSize(self::HEADER_SIZE, self::HEADER_SIZE);
        $printer->setEmphasis(true);
        $printer->text("Mohsin Medical Complex\n\n");
        $printer->setTextSize(2, 1);
        $printer->setEmphasis(false);
        $printer->text($this->getReceiptType()."\n\n");
        $printer->setJustification(EscposPrinter::JUSTIFY_LEFT);
        $printer->setTextSize(self::BODY_SIZE, 1);
        $printer->setEmphasis(true);
        $printer->text($this->getBodyContent());

        $smallContent = $this->getSmallBodyContent();
        if ($smallContent !== '') {
            $printer->selectPrintMode(EscposPrinter::MODE_FONT_B);
            $printer->setEmphasis(false);
            $printer->text($smallContent);
            $printer->selectPrintMode(EscposPrinter::MODE_FONT_A);
            $printer->setEmphasis(true);
        }

        $printer->setJustification(EscposPrinter::JUSTIFY_CENTER);
        $printer->selectPrintMode(EscposPrinter::MODE_FONT_B);
        $printer->setEmphasis(false);
        $printer->text($this->footer());
        $printer->selectPrintMode(EscposPrinter::MODE_FONT_A);
        $printer->setTextSize(1, 1);
    }

    abstract protected function getReceiptType(): string;

    abstract protected function getBodyContent(): string;

    protected function getSmallBodyContent(): string
    {
        return '';
    }

    public function toEscPosText(): string
    {
        return "Mohsin Medical Complex\n"
            .$this->getReceiptType()."\n"
            .$this->getBodyContent()
            .$this->getSmallBodyContent()
            .$this->footer();
    }

    protected function footer(): string
    {
        $contact = config('printing.footer_contact', '0426662345');

        return "\nThanks for coming.\n"
            .'Contact : '.$contact."\n"
            .'Printed at '.Carbon::now()->format('M j, Y h:i A')."\n";
    }
}
