<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DoctorPayout extends Model
{
    protected $fillable = [
        'doctor_id',
        'amount',
        'period_from',
        'period_to',
        'shift_id',
        'paid_by',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'doctor_id' => 'integer',
            'amount' => 'integer',
            'period_from' => 'date',
            'period_to' => 'date',
            'shift_id' => 'integer',
            'paid_by' => 'integer',
        ];
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function paidBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(DoctorPayoutLedger::class);
    }
}
