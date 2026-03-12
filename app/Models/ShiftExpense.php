<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShiftExpense extends Model
{
    use HasFactory;

    protected $fillable = [
        'shift_id',
        'amount',
        'description',
        'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'shift_id' => 'integer',
            'recorded_by' => 'integer',
            'amount' => 'decimal:2',
        ];
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
