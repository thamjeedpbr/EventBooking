<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTicketRequest;
use App\Http\Requests\UpdateTicketRequest;
use App\Services\TicketService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TicketController extends Controller
{
    use ApiResponse;

    /**
     * Store a newly created ticket for an event (organizer only)
     */
    public function store(StoreTicketRequest $request): JsonResponse
    {
        try {
            $result = TicketService::createTicket(
                $request->event_id,
                $request->validated(),
                $request->user()->id
            );

            if (!$result['success']) {
                if (str_contains($result['message'], 'permission')) {
                    return $this->forbiddenResponse($result['message']);
                }
                return $this->errorResponse($result['message'], Response::HTTP_BAD_REQUEST);
            }

            return $this->createdResponse($result['data'], $result['message']);
        } catch (\Exception $e) {
            return $this->serverErrorResponse('Failed to create ticket: ' . $e->getMessage());
        }
    }

    /**
     * Update the specified ticket (organizer only)
     */
    public function update(UpdateTicketRequest $request, int $id): JsonResponse
    {
        try {
            $result = TicketService::updateTicket(
                $id,
                $request->validated(),
                $request->user()->id
            );

            if (!$result['success']) {
                if (str_contains($result['message'], 'permission')) {
                    return $this->forbiddenResponse($result['message']);
                }
                if (str_contains($result['message'], 'Cannot reduce')) {
                    return $this->conflictResponse($result['message']);
                }
                return $this->errorResponse($result['message'], Response::HTTP_BAD_REQUEST);
            }

            return $this->successResponse($result['data'], $result['message']);
        } catch (\Exception $e) {
            return $this->serverErrorResponse('Failed to update ticket: ' . $e->getMessage());
        }
    }

    /**
     * Remove the specified ticket (organizer only)
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        try {
            $result = TicketService::deleteTicket($id, $request->user()->id);

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
            return $this->serverErrorResponse('Failed to delete ticket: ' . $e->getMessage());
        }
    }
}
