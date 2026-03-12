<?php

namespace App\Printing;

use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;

/**
 * Fixes COM/LPT port handling on Windows.
 *
 * PHP's file_put_contents("COM8", $data) with CWD=public/ creates public/COM8
 * instead of opening the device. Using the \\.\ prefix forces the real COM port.
 */
class WindowsReceiptPrintConnector extends WindowsPrintConnector
{
    protected function runWrite($data, $filename): bool
    {
        if (preg_match('/^(COM\d|LPT\d)$/i', $filename)) {
            $filename = '\\\\.\\'.$filename;
        }

        return file_put_contents($filename, $data) !== false;
    }
}
