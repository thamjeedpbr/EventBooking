<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Ticket;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TicketService
{
    /**
     * Create a new ticket
     */
    public static function createTicket(int $eventId, array $data, int $userId): array
    {
        try {
            return DB::transaction(function () use ($eventId, $data, $userId) {
                $event = Event::lockForUpdate()->findOrFail($eventId);

                // Check ownership for organizers
                if ($event->created_by !== $userId) {
                    throw new Exception('You do not have permission to add tickets to this event');
                }

                $ticket = Ticket::create([
                    'event_id' => $eventId,
                    'type' => $data['type'],
                    'price' => $data['price'],
                    'quantity' => $data['quantity'],
                    'available_quantity' => $data['quantity'],
                ]);

                Event::clearCache();

                return [
                    'success' => true,
                    'message' => 'Ticket created successfully',
                    'data' => $ticket->load('event'),
                ];
            });
        } catch (Exception $e) {
            Log::error('Ticket creation failed: ' . $e->getMessage(), [
                'event_id' => $eventId,
                'user_id' => $userId,
                'data' => $data,
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
     * Update a ticket
     */
    public static function updateTicket(int $ticketId, array $data, int $userId): array
    {
        try {
            return DB::transaction(function () use ($ticketId, $data, $userId) {
                $ticket = Ticket::with('event')->lockForUpdate()->findOrFail($ticketId);

                // Check ownership for organizers
                if ($ticket->event->created_by !== $userId) {
                    throw new Exception('You do not have permission to update this ticket');
                }

                // Check if there are bookings
                $hasBookings = $ticket->bookings()
                    ->whereIn('status', ['pending', 'confirmed'])
                    ->exists();

                // Prevent quantity reduction if it would affect existing bookings
                if (isset($data['quantity']) && $hasBookings) {
                    $bookedQuantity = $ticket->quantity - $ticket->available_quantity;
                    if ($data['quantity'] < $bookedQuantity) {
                        throw new Exception('Cannot reduce quantity below booked amount (' . $bookedQuantity . ')');
                    }
                    // Adjust available quantity accordingly
                    $data['available_quantity'] = $data['quantity'] - $bookedQuantity;
                }

                $ticket->update([
                    'type' => $data['type'] ?? $ticket->type,
                    'price' => $data['price'] ?? $ticket->price,
                    'quantity' => $data['quantity'] ?? $ticket->quantity,
                    'available_quantity' => $data['available_quantity'] ?? $ticket->available_quantity,
                ]);

                Event::clearCache();

                return [
                    'success' => true,
                    'message' => 'Ticket updated successfully',
                    'data' => $ticket->fresh(['event']),
                ];
            });
        } catch (Exception $e) {
            Log::error('Ticket update failed: ' . $e->getMessage(), [
                'ticket_id' => $ticketId,
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
     * Delete a ticket
     */
    public static function deleteTicket(int $ticketId, int $userId): array
    {
        try {
            return DB::transaction(function () use ($ticketId, $userId) {
                $ticket = Ticket::with('event')->lockForUpdate()->findOrFail($ticketId);

                // Check ownership for organizers
                if ($ticket->event->created_by !== $userId) {
                    throw new Exception('You do not have permission to delete this ticket');
                }

                // Check if there are confirmed bookings
                $hasConfirmedBookings = $ticket->bookings()
                    ->where('status', 'confirmed')
                    ->exists();

                if ($hasConfirmedBookings) {
                    throw new Exception('Cannot delete ticket with confirmed bookings');
                }

                $ticket->delete();
                Event::clearCache();

                return [
                    'success' => true,
                    'message' => 'Ticket deleted successfully',
                    'data' => null,
                ];
            });
        } catch (Exception $e) {
            Log::error('Ticket deletion failed: ' . $e->getMessage(), [
                'ticket_id' => $ticketId,
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
     * Get ticket details
     */
    public static function getTicketDetails(int $ticketId): array
    {
        try {
            $ticket = Ticket::with(['event.creator'])->findOrFail($ticketId);

            return [
                'success' => true,
                'message' => 'Ticket details retrieved successfully',
                'data' => $ticket,
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Ticket not found',
                'data' => null,
            ];
        }
    }
}
