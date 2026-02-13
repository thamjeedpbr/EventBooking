<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Ticket;
use App\Notifications\BookingConfirmedNotification;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BookingService
{
    /**
     * Create a new booking
     */
    public static function createBooking(
        int $userId,
        int $ticketId,
        int $quantity
    ): array {
        try {
            return DB::transaction(function () use ($userId, $ticketId, $quantity) {
                // Lock ticket for update to prevent race conditions
                $ticket = Ticket::with('event')->lockForUpdate()->findOrFail($ticketId);

                // Check if event is still upcoming
                if ($ticket->event->isPast()) {
                    throw new Exception('Cannot book tickets for past events');
                }

                // Check ticket availability
                if (!$ticket->isAvailable($quantity)) {
                    throw new Exception('Insufficient tickets available. Only ' . $ticket->available_quantity . ' tickets left');
                }

                // Check for duplicate booking
                $existingBooking = Booking::where('user_id', $userId)
                    ->where('ticket_id', $ticketId)
                    ->whereIn('status', ['pending', 'confirmed'])
                    ->first();

                if ($existingBooking) {
                    throw new Exception('You already have a booking for this ticket');
                }

                // Calculate total amount
                $totalAmount = $ticket->price * $quantity;

                // Create booking
                $booking = Booking::create([
                    'user_id' => $userId,
                    'ticket_id' => $ticketId,
                    'quantity' => $quantity,
                    'total_amount' => $totalAmount,
                    'status' => 'pending',
                ]);

                // Decrease ticket availability
                $decreased = Ticket::decreaseAvailability($ticketId, $quantity);

                if (!$decreased) {
                    throw new Exception('Failed to reserve tickets. Please try again');
                }

                return [
                    'success' => true,
                    'message' => 'Booking created successfully',
                    'data' => $booking->load(['ticket.event', 'user']),
                ];
            });
        } catch (Exception $e) {
            Log::error('Booking creation failed: ' . $e->getMessage(), [
                'user_id' => $userId,
                'ticket_id' => $ticketId,
                'quantity' => $quantity,
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
            ];
        }
    }

    /**
     * Cancel a booking
     */
    public static function cancelBooking(int $bookingId, int $userId): array
    {
        try {
            return DB::transaction(function () use ($bookingId, $userId) {
                $booking = Booking::with(['ticket.event', 'payment'])
                    ->lockForUpdate()
                    ->findOrFail($bookingId);

                // Check ownership
                if ($booking->user_id !== $userId) {
                    throw new Exception('You do not have permission to cancel this booking');
                }

                // Check if already cancelled
                if ($booking->isCancelled()) {
                    throw new Exception('Booking is already cancelled');
                }

                // Check if event has passed
                if ($booking->ticket->event->isPast()) {
                    throw new Exception('Cannot cancel bookings for past events');
                }

                // If payment was made, process refund
                if ($booking->payment && $booking->payment->isSuccessful()) {
                    $refundResult = PaymentService::refundPayment(
                        $booking->payment->id,
                        'Cancelled by customer'
                    );

                    if (!$refundResult['success']) {
                        throw new Exception('Failed to process refund: ' . $refundResult['message']);
                    }
                }

                // Cancel booking
                $cancelled = Booking::cancelBooking($bookingId);

                if (!$cancelled) {
                    throw new Exception('Failed to cancel booking');
                }

                return [
                    'success' => true,
                    'message' => 'Booking cancelled successfully',
                    'data' => $booking->fresh(),
                ];
            });
        } catch (Exception $e) {
            Log::error('Booking cancellation failed: ' . $e->getMessage(), [
                'booking_id' => $bookingId,
                'user_id' => $userId,
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
            ];
        }
    }

    /**
     * Get user bookings
     */
    public static function getUserBookings(int $userId, array $filters = []): array
    {
        try {
            $query = Booking::with(['ticket.event', 'payment'])
                ->where('user_id', $userId);

            if (isset($filters['status'])) {
                $query->where('status', $filters['status']);
            }

            if (isset($filters['from_date'])) {
                $query->whereHas('ticket.event', function ($q) use ($filters) {
                    $q->whereDate('date', '>=', $filters['from_date']);
                });
            }

            if (isset($filters['to_date'])) {
                $query->whereHas('ticket.event', function ($q) use ($filters) {
                    $q->whereDate('date', '<=', $filters['to_date']);
                });
            }

            $bookings = $query->latest()->get();

            return [
                'success' => true,
                'message' => 'Bookings retrieved successfully',
                'data' => $bookings,
            ];
        } catch (Exception $e) {
            Log::error('Get user bookings failed: ' . $e->getMessage());

            return [
                'success' => false,
                'message' => 'Failed to retrieve bookings',
                'data' => [],
            ];
        }
    }

    /**
     * Get booking details
     */
    public static function getBookingDetails(int $bookingId, int $userId): array
    {
        try {
            $booking = Booking::with(['ticket.event', 'payment', 'user'])
                ->where('id', $bookingId)
                ->where('user_id', $userId)
                ->firstOrFail();

            return [
                'success' => true,
                'message' => 'Booking details retrieved successfully',
                'data' => $booking,
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Booking not found or access denied',
                'data' => null,
            ];
        }
    }

    /**
     * Send booking confirmation notification
     */
    public static function sendConfirmationNotification(Booking $booking): void
    {
        try {
            $booking->load(['user', 'ticket.event']);
            $booking->user->notify(new BookingConfirmedNotification($booking));
        } catch (Exception $e) {
            Log::error('Failed to send booking confirmation notification: ' . $e->getMessage(), [
                'booking_id' => $booking->id,
            ]);
        }
    }
}
