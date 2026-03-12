<?php

namespace App\Printing;

use Mike42\Escpos\PrintConnectors\FilePrintConnector;
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;
use Mike42\Escpos\Printer;

class ReceiptPrinterConnectorFactory
{
    /**
     * Create a print connector — accepts either a COM port (e.g. "COM8")
     * or a Windows printer name (e.g. "Tysso Thermal Receipt Printer").
     */
    public function connector(string $port): FilePrintConnector|WindowsPrintConnector
    {
        if (preg_match('/^COM\d+$/i', $port)) {
            // COM ports require UNC path format on Windows (required for COM5+, safe for all)
            return new FilePrintConnector('\\\\.\\'.$port);
        }

        // Treat anything else as a Windows printer name
        return new WindowsPrintConnector($port);
    }

    public function printer(string $port): Printer
    {
        return new Printer($this->connector($port));
    }
}