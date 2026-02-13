<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEventRequest;
use App\Http\Requests\UpdateEventRequest;
use App\Services\EventService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EventController extends Controller
{
    use ApiResponse;

    /**
     * Display a listing of events with pagination, search, and filters
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = [
                'search' => $request->input('search'),
                'date' => $request->input('date'),
                'from_date' => $request->input('from_date'),
                'to_date' => $request->input('to_date'),
                'location' => $request->input('location'),
                'created_by' => $request->input('created_by'),
            ];

            $perPage = $request->input('per_page', 15);
            $events = EventService::getEvents($filters, $perPage);

            return $this->paginatedResponse($events, 'Events retrieved successfully');
        } catch (\Exception $e) {
            return $this->serverErrorResponse('Failed to retrieve events: ' . $e->getMessage());
        }
    }

    /**
     * Display the specified event with tickets
     */
    public function show(int $id): JsonResponse
    {
        try {
            $result = EventService::getEventById($id);

            if (!$result['success']) {
                return $this->notFoundResponse($result['message']);
            }

            return $this->successResponse($result['data'], $result['message']);
        } catch (\Exception $e) {
            return $this->serverErrorResponse('Failed to retrieve event');
        }
    }

    /**
     * Store a newly created event (organizer only)
     */
    public function store(StoreEventRequest $request): JsonResponse
    {
        try {
            $result = EventService::createEvent(
                $request->validated(),
                $request->user()->id
            );

            if (!$result['success']) {
                return $this->errorResponse($result['message'], Response::HTTP_BAD_REQUEST);
            }

            return $this->createdResponse($result['data'], $result['message']);
        } catch (\Exception $e) {
            return $this->serverErrorResponse('Failed to create event: ' . $e->getMessage());
        }
    }

    /**
     * Update the specified event (organizer only, own events)
     */
    public function update(UpdateEventRequest $request, int $id): JsonResponse
    {
        try {
            $result = EventService::updateEvent(
                $id,
                $request->validated(),
                $request->user()->id
            );

            if (!$result['success']) {
                if (str_contains($result['message'], 'permission')) {
                    return $this->forbiddenResponse($result['message']);
                }
                return $this->errorResponse($result['message'], Response::HTTP_BAD_REQUEST);
            }

            return $this->successResponse($result['data'], $result['message']);
        } catch (\Exception $e) {
            return $this->serverErrorResponse('Failed to update event: ' . $e->getMessage());
        }
    }

    /**
     * Remove the specified event (organizer only, own events)
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        try {
            $result = EventService::deleteEvent($id, $request->user()->id);

            if (!$result['success']) {
                if (str_contains($result['message'], 'permission')) {
                    return $this->forbiddenResponse($result['message']);
                }
                if (str_contains($result['message'], 'confirmed bookings')) {
                    return $this->conflictResponse($result['message']);
                }
                return $this->errorResponse($result['message'], Response::HTTP_BAD_REQUEST);
            }

            return $this->successResponse(null, $result['message']);
        } catch (\Exception $e) {
            return $this->serverErrorResponse('Failed to delete event: ' . $e->getMessage());
        }
    }
}
