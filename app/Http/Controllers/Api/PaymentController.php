<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProcessPaymentRequest;
use App\Models\Booking;
use App\Services\BookingService;
use App\Services\PaymentService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PaymentController extends Controller
{
    use ApiResponse;

    /**
     * Process payment for a booking
     */
    public function processPayment(ProcessPaymentRequest $request): JsonResponse
    {
        try {
            // Verify booking belongs to user
            $booking = Booking::where('id', $request->booking_id)
                ->where('user_id', $request->user()->id)
                ->first();

            if (!$booking) {
                return $this->forbiddenResponse('You do not have permission to pay for this booking');
            }

            $result = PaymentService::processPayment(
                $request->booking_id,
                $request->payment_method,
                $request->payment_details ?? []
            );

            if (!$result['success']) {
                if (str_contains($result['message'], 'not in pending')) {
                    return $this->conflictResponse($result['message']);
                }
                return $this->errorResponse($result['message'], Response::HTTP_BAD_REQUEST);
            }

            // Send booking confirmation notification
            if (isset($result['data']['booking'])) {
                BookingService::sendConfirmationNotification($result['data']['booking']);
            }

            return $this->successResponse($result['data'], $result['message']);
        } catch (\Exception $e) {
            return $this->serverErrorResponse('Failed to process payment: ' . $e->getMessage());
        }
    }

    /**
     * Get payment details
     */
    public function show(Request $request, int $id): JsonResponse
    {
        try {
            $result = PaymentService::getPaymentStatus($id);

            if (!$result['success']) {
                return $this->notFoundResponse($result['message']);
            }

            // Verify payment belongs to user
            if ($result['data']->booking->user_id !== $request->user()->id) {
                return $this->forbiddenResponse('You do not have permission to view this payment');
            }

            return $this->successResponse($result['data'], $result['message']);
        } catch (\Exception $e) {
            return $this->serverErrorResponse('Failed to retrieve payment details');
        }
    }
}
