<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Ticket;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EventService
{
    /**
     * Get paginated events with caching
     */
    public static function getEvents(array $filters = [], int $perPage = 15)
    {
        try {
            // Create cache key based on filters
            $cacheKey = 'events_' . md5(json_encode($filters) . $perPage);

            return Cache::remember($cacheKey, 3600, function () use ($filters, $perPage) {
                $query = Event::with(['creator:id,name', 'tickets'])
                    ->select('events.*');

                // Apply search filter
                if (!empty($filters['search'])) {
                    $query->searchMultiple($filters['search'], ['title', 'description', 'location']);
                }

                // Apply date filter
                if (!empty($filters['date'])) {
                    $query->filterByDate($filters['date']);
                }

                // Apply date range filter
                if (!empty($filters['from_date']) || !empty($filters['to_date'])) {
                    $query->filterByDateRange(
                        $filters['from_date'] ?? null,
                        $filters['to_date'] ?? null
                    );
                }

                // Apply location filter
                if (!empty($filters['location'])) {
                    $query->filterByLocation($filters['location']);
                }

                // Apply creator filter
                if (!empty($filters['created_by'])) {
                    $query->where('created_by', $filters['created_by']);
                }

                // Order by date
                $query->orderBy('date', 'asc');

                return $query->paginate($perPage);
            });
        } catch (Exception $e) {
            Log::error('Get events failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get event by ID
     */
    public static function getEventById(int $eventId): array
    {
        try {
            $cacheKey = 'event_' . $eventId;

            $event = Cache::remember($cacheKey, 3600, function () use ($eventId) {
                return Event::with(['creator:id,name,email', 'tickets'])->findOrFail($eventId);
            });

            return [
                'success' => true,
                'message' => 'Event retrieved successfully',
                'data' => $event,
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Event not found',
                'data' => null,
            ];
        }
    }

    /**
     * Create a new event
     */
    public static function createEvent(array $data, int $userId): array
    {
        try {
            return DB::transaction(function () use ($data, $userId) {
                $event = Event::create([
                    'title' => $data['title'],
                    'description' => $data['description'],
                    'date' => $data['date'],
                    'location' => $data['location'],
                    'created_by' => $userId,
                ]);

                // Create tickets if provided
                if (!empty($data['tickets'])) {
                    foreach ($data['tickets'] as $ticketData) {
                        Ticket::create([
                            'event_id' => $event->id,
                            'type' => $ticketData['type'],
                            'price' => $ticketData['price'],
                            'quantity' => $ticketData['quantity'],
                            'available_quantity' => $ticketData['quantity'],
                        ]);
                    }
                }

                return [
                    'success' => true,
                    'message' => 'Event created successfully',
                    'data' => $event->load('tickets'),
                ];
            });
        } catch (Exception $e) {
            Log::error('Event creation failed: ' . $e->getMessage(), [
                'user_id' => $userId,
                'data' => $data,
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to create event: ' . $e->getMessage(),
                'data' => null,
            ];
        }
    }

    /**
     * Update an event
     */
    public static function updateEvent(int $eventId, array $data, int $userId): array
    {
        try {
            return DB::transaction(function () use ($eventId, $data, $userId) {
                $event = Event::lockForUpdate()->findOrFail($eventId);

                // Check ownership for organizers
                if ($event->created_by !== $userId) {
                    throw new Exception('You do not have permission to update this event');
                }

                $event->update([
                    'title' => $data['title'] ?? $event->title,
                    'description' => $data['description'] ?? $event->description,
                    'date' => $data['date'] ?? $event->date,
                    'location' => $data['location'] ?? $event->location,
                ]);

                return [
                    'success' => true,
                    'message' => 'Event updated successfully',
                    'data' => $event->fresh(['tickets']),
                ];
            });
        } catch (Exception $e) {
            Log::error('Event update failed: ' . $e->getMessage(), [
                'event_id' => $eventId,
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
     * Delete an event
     */
    public static function deleteEvent(int $eventId, int $userId): array
    {
        try {
            return DB::transaction(function () use ($eventId, $userId) {
                $event = Event::with('tickets.bookings')->lockForUpdate()->findOrFail($eventId);

                // Check ownership for organizers
                if ($event->created_by !== $userId) {
                    throw new Exception('You do not have permission to delete this event');
                }

                // Check if there are confirmed bookings
                $hasConfirmedBookings = $event->tickets()
                    ->whereHas('bookings', function ($query) {
                        $query->where('status', 'confirmed');
                    })
                    ->exists();

                if ($hasConfirmedBookings) {
                    throw new Exception('Cannot delete event with confirmed bookings');
                }

                $event->delete();

                return [
                    'success' => true,
                    'message' => 'Event deleted successfully',
                    'data' => null,
                ];
            });
        } catch (Exception $e) {
            Log::error('Event deletion failed: ' . $e->getMessage(), [
                'event_id' => $eventId,
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
}
