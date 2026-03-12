<?php

namespace App\Printing;

use Mike42\Escpos\Printer;

class ReceiptPrinterConnectorFactory
{
    /**
     * Create a print connector for the given port (e.g. COM8) or printer name.
     */
    public function connector(string $port): WindowsReceiptPrintConnector
    {
        return new WindowsReceiptPrintConnector($port);
    }

    /**
     * Create a Printer instance for the given port.
     *
     * @return Printer
     */
    public function printer(string $port)
    {
        return new Printer($this->connector($port));
    }
}
