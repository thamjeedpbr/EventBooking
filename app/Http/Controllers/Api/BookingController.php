<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBookingRequest;
use App\Services\BookingService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BookingController extends Controller
{
    use ApiResponse;

    /**
     * Create a new booking (customer only)
     */
    public function store(StoreBookingRequest $request): JsonResponse
    {
        try {
            $result = BookingService::createBooking(
                $request->user()->id,
                $request->ticket_id,
                $request->quantity
            );

            if (!$result['success']) {
                if (str_contains($result['message'], 'already have a booking')) {
                    return $this->conflictResponse($result['message']);
                }
                if (str_contains($result['message'], 'Insufficient tickets')) {
                    return $this->conflictResponse($result['message']);
                }
                if (str_contains($result['message'], 'past events')) {
                    return $this->errorResponse($result['message'], Response::HTTP_BAD_REQUEST);
                }
                return $this->errorResponse($result['message'], Response::HTTP_BAD_REQUEST);
            }

            return $this->createdResponse($result['data'], $result['message']);
        } catch (\Exception $e) {
            return $this->serverErrorResponse('Failed to create booking: ' . $e->getMessage());
        }
    }

    /**
     * Get user's bookings
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = [
                'status' => $request->input('status'),
                'from_date' => $request->input('from_date'),
                'to_date' => $request->input('to_date'),
            ];

            $result = BookingService::getUserBookings($request->user()->id, $filters);

            if (!$result['success']) {
                return $this->errorResponse($result['message'], Response::HTTP_BAD_REQUEST);
            }

            return $this->successResponse($result['data'], $result['message']);
        } catch (\Exception $e) {
            return $this->serverErrorResponse('Failed to retrieve bookings: ' . $e->getMessage());
        }
    }

    /**
     * Cancel a booking
     */
    public function cancel(Request $request, int $id): JsonResponse
    {
        try {
            $result = BookingService::cancelBooking($id, $request->user()->id);

            if (!$result['success']) {
                if (str_contains($result['message'], 'permission')) {
                    return $this->forbiddenResponse($result['message']);
                }
                if (str_contains($result['message'], 'already cancelled')) {
                    return $this->conflictResponse($result['message']);
                }
                if (str_contains($result['message'], 'past events')) {
                    return $this->errorResponse($result['message'], Response::HTTP_BAD_REQUEST);
                }
                return $this->errorResponse($result['message'], Response::HTTP_BAD_REQUEST);
            }

            return $this->successResponse($result['data'], $result['message']);
        } catch (\Exception $e) {
            return $this->serverErrorResponse('Failed to cancel booking: ' . $e->getMessage());
        }
    }
}
