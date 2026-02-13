<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ticket extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'event_id',
        'type',
        'price',
        'quantity',
        'available_quantity',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'quantity' => 'integer',
            'available_quantity' => 'integer',
        ];
    }

    /**
     * Get the event this ticket belongs to
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * Get all bookings for this ticket
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * Check if tickets are available
     */
    public function isAvailable(int $requestedQuantity = 1): bool
    {
        return $this->available_quantity >= $requestedQuantity;
    }

    /**
     * Decrease available quantity
     */
    public static function decreaseAvailability(int $ticketId, int $quantity): bool
    {
        return self::where('id', $ticketId)
            ->where('available_quantity', '>=', $quantity)
            ->decrement('available_quantity', $quantity) > 0;
    }

    /**
     * Increase available quantity (for cancellations)
     */
    public static function increaseAvailability(int $ticketId, int $quantity): bool
    {
        return self::where('id', $ticketId)
            ->increment('available_quantity', $quantity) > 0;
    }

    /**
     * Check if ticket is sold out
     */
    public function isSoldOut(): bool
    {
        return $this->available_quantity <= 0;
    }
}
