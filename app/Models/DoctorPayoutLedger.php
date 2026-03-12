<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DoctorPayoutLedger extends Model
{
    protected $table = 'doctor_payout_ledger';

    protected $fillable = [
        'doctor_payout_id',
        'invoice_service_id',
        'share_amount',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'doctor_payout_id' => 'integer',
            'invoice_service_id' => 'integer',
            'share_amount' => 'integer',
        ];
    }

    public function doctorPayout(): BelongsTo
    {
        return $this->belongsTo(DoctorPayout::class);
    }

    public function invoiceService(): BelongsTo
    {
        return $this->belongsTo(InvoiceService::class);
    }
}
