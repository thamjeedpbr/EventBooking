<?php

namespace App\Models;

use App\Traits\CommonQueryScopes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;

class Event extends Model
{
    use HasFactory, SoftDeletes, CommonQueryScopes;

    protected $fillable = [
        'title',
        'description',
        'date',
        'location',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'datetime',
        ];
    }

    /**
     * Get the user who created the event
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get all tickets for the event
     */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    /**
     * Clear event cache
     */
    public static function clearCache(): void
    {
        // Clear common cache patterns
        Cache::forget('events_list');

        // Clear paginated event lists (common filter combinations)
        $filterPatterns = ['', 'search', 'location', 'date', 'from_date', 'to_date'];
        foreach ($filterPatterns as $pattern) {
            for ($page = 1; $page <= 10; $page++) {
                $key = 'events_' . md5(json_encode(['search' => $pattern]) . '15');
                Cache::forget($key);
            }
        }

        // Note: In production, consider using Redis for better cache management with tags
    }

    /**
     * Boot the model
     */
    protected static function boot(): void
    {
        parent::boot();

        static::saved(function () {
            self::clearCache();
        });

        static::deleted(function () {
            self::clearCache();
        });
    }

    /**
     * Check if event is past
     */
    public function isPast(): bool
    {
        return $this->date->isPast();
    }

    /**
     * Check if event is upcoming
     */
    public function isUpcoming(): bool
    {
        return $this->date->isFuture();
    }

    /**
     * Get available tickets count
     */
    public function getAvailableTicketsAttribute(): int
    {
        return $this->tickets()->sum('available_quantity');
    }
}
