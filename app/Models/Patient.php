<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Patient extends Model
{
    use HasFactory;

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Patient $patient): void {
            if ($patient->mr_number === null || $patient->mr_number === '') {
                $patient->mr_number = self::generateMrNumber();
            }
        });
    }

    public static function generateMrNumber(): string
    {
        do {
            $candidate = 'MR-'.str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        } while (self::query()->where('mr_number', $candidate)->exists());

        return $candidate;
    }

    public static function findByMrNumber(string $input): ?self
    {
        $trimmed = trim($input);
        if ($trimmed === '') {
            return null;
        }
        $normalized = strtoupper($trimmed);
        if (! str_starts_with($normalized, 'MR-')) {
            $normalized = 'MR-'.str_pad($normalized, 6, '0', STR_PAD_LEFT);
        } else {
            $suffix = substr($normalized, 3);
            if (ctype_digit($suffix)) {
                $normalized = 'MR-'.str_pad($suffix, 6, '0', STR_PAD_LEFT);
            }
        }

        return self::query()->where('mr_number', $normalized)->first();
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'mr_number',
        'name',
        'gender',
        'dob',
        'relation_to_head',
        'family_id',
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
            'dob' => 'date',
            'family_id' => 'integer',
        ];
    }

    public function family(): BelongsTo
    {
        return $this->belongsTo(Family::class);
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
}
