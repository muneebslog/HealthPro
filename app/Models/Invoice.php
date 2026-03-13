<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'patient_id',
        'visit_id',
        'procedure_admission_id',
        'total_amount',
        'paid_amount',
        'status',
        'shift_id',
        'created_by',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'patient_id' => 'integer',
            'visit_id' => 'integer',
            'procedure_admission_id' => 'integer',
            'total_amount' => 'integer',
            'paid_amount' => 'integer',
            'shift_id' => 'integer',
            'created_by' => 'integer',
        ];
    }

    public function procedureAdmission(): BelongsTo
    {
        return $this->belongsTo(ProcedureAdmission::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function remainingBalance(): int
    {
        return max(0, $this->total_amount - $this->paid_amount);
    }

    public function isProcedure(): bool
    {
        return $this->procedure_admission_id !== null;
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function invoiceServices(): HasMany
    {
        return $this->hasMany(InvoiceService::class);
    }
}
