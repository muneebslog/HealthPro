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

    'default_printer_port' => env('RECEIPT_PRINTER_PORT', 'COM4'),

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
