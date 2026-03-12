<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shift extends Model
{
    /** @use HasFactory<\Database\Factories\ShiftFactory> */
    use HasFactory;

    protected $fillable = [
        'opened_at',
        'opening_cash',
        'expected_cash',
        'cash_in_hand',
        'closed_at',
        'opened_by',
        'closed_by',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'opened_by' => 'integer',
            'closed_by' => 'integer',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'opening_cash' => 'float',
            'expected_cash' => 'float',
            'cash_in_hand' => 'float',
        ];
    }

    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public static function current(): ?self
    {
        return static::whereNull('closed_at')->latest('opened_at')->first();
    }

    public function visits(): HasMany
    {
        return $this->hasMany(Visit::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function queueTokens(): HasMany
    {
        return $this->hasMany(QueueToken::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(ShiftExpense::class);
    }

    public function doctorPayouts(): HasMany
    {
        return $this->hasMany(DoctorPayout::class);
    }
}
