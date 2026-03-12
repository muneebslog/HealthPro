<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServicePrice extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'service_id',
        'doctor_id',
        'price',
        'doctor_share',
        'hospital_share',
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
            'service_id' => 'integer',
            'doctor_id' => 'integer',
        ];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function invoiceServices(): HasMany
    {
        return $this->hasMany(InvoiceService::class);
    }

    /**
     * Calculated doctor share amount (stored as doctor_share when set).
     */
    public function getCalculatedDoctorShareAmount(): ?int
    {
        return $this->doctor_share;
    }

    /**
     * Calculated hospital share amount (stored as hospital_share).
     */
    public function getCalculatedHospitalShareAmount(): int
    {
        return $this->hospital_share;
    }

    /**
     * Doctor share as percentage of price (0–100). Null if no doctor share.
     */
    public function getDoctorSharePercentage(): ?float
    {
        if ($this->price <= 0 || $this->doctor_share === null) {
            return null;
        }

        return round($this->doctor_share / $this->price * 100, 2);
    }

    /**
     * Hospital share as percentage of price (0–100).
     */
    public function getHospitalSharePercentage(): float
    {
        if ($this->price <= 0) {
            return 0.0;
        }

        return round($this->hospital_share / $this->price * 100, 2);
    }
}
