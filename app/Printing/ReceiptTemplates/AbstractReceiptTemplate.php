<?php

namespace App\Printing\ReceiptTemplates;

use Carbon\Carbon;

abstract class AbstractReceiptTemplate
{
    protected function header(string $type): string
    {
        return "Mohsin Medical Complex\n"
            .$type."\n";
    }

    protected function footer(): string
    {
        $contact = config('printing.footer_contact', '0426662345');

        return "Thanks for coming.\n"
            .'Contact : '.$contact."\n"
            .'Printed at '.Carbon::now()->format('M j, Y h:i A')."\n";
    }

    abstract public function toEscPosText(): string;
}
