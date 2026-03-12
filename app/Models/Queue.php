<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Queue extends Model
{
    use HasFactory;

    public const APPOINTMENT_SERVICE_ID = 1;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'service_id',
        'doctor_id',
        'queue_type',
        'current_token',
        'status',
        'started_at',
        'ended_at',
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
            'service_id' => 'integer',
            'doctor_id' => 'integer',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function queueTokens(): HasMany
    {
        return $this->hasMany(QueueToken::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active')->whereNull('ended_at');
    }

    public function scopeForShiftClose($query, int $shiftId)
    {
        return $query->where('shift_id', $shiftId);
    }

    public function scopeDailyAppointmentToClose($query)
    {
        return $query->where('service_id', self::APPOINTMENT_SERVICE_ID)
            ->where('doctor_id', '>', 1);
    }
}
