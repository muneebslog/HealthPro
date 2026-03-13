<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Receipt printer port
    |--------------------------------------------------------------------------
    |
    | The printer identifier (e.g. COM4 on Windows) for the ESC/POS thermal
    | receipt printer. Used by WindowsPrintConnector.
    |
    */

    'default_printer_port' => env('RECEIPT_PRINTER_PORT', 'COM6'),

    /*
    |--------------------------------------------------------------------------
    | Receipt printer name (alternative to port)
    |--------------------------------------------------------------------------
    |
    | Use the Windows printer name (e.g. "Tysso Thermal Receipt Printer") instead
    | of the COM port. Some USB printers work better with the printer name.
    |
    */

    'printer_name' => env('RECEIPT_PRINTER_NAME'),

    /*
    |--------------------------------------------------------------------------
    | Receipt footer contact
    |--------------------------------------------------------------------------
    |
    | Contact number shown on printed receipts.
    |
    */

    'footer_contact' => env('RECEIPT_FOOTER_CONTACT', '0426662345'),

];
