<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ProcedureAdmission extends Model
{
    /** @use HasFactory<\Database\Factories\ProcedureAdmissionFactory> */
    use HasFactory;

    protected $fillable = [
        'patient_id',
        'package_name',
        'full_price',
        'operation_doctor_id',
        'operation_date',
        'room',
        'bed',
        'shift_id',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'patient_id' => 'integer',
            'full_price' => 'integer',
            'operation_doctor_id' => 'integer',
            'operation_date' => 'date',
            'shift_id' => 'integer',
            'created_by' => 'integer',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function operationDoctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class, 'operation_doctor_id');
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }
}
