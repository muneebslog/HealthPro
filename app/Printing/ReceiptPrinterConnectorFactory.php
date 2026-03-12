<?php

namespace App\Printing;

use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;
use Mike42\Escpos\Printer;

class ReceiptPrinterConnectorFactory
{
    /**
     * Create a print connector for the given port (e.g. COM4 on Windows).
     */
    public function connector(string $port): WindowsPrintConnector
    {
        return new WindowsPrintConnector($port);
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
