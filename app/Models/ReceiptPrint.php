<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReceiptPrint extends Model
{
    protected $fillable = [
        'print_type',
        'invoice_id',
        'shift_id',
        'doctor_payout_id',
        'printed_at',
        'printed_by',
        'printer_identifier',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'invoice_id' => 'integer',
            'shift_id' => 'integer',
            'doctor_payout_id' => 'integer',
            'printed_by' => 'integer',
            'printed_at' => 'datetime',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function doctorPayout(): BelongsTo
    {
        return $this->belongsTo(DoctorPayout::class);
    }

    public function printedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'printed_by');
    }
}
