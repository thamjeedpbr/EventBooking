<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Payment;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentService
{
    /**
     * Process payment for a booking
     */
    public static function processPayment(
        int $bookingId,
        string $paymentMethod = 'mock',
        array $paymentDetails = []
    ): array {
        try {
            return DB::transaction(function () use ($bookingId, $paymentMethod, $paymentDetails) {
                $booking = Booking::with('ticket')->lockForUpdate()->findOrFail($bookingId);

                if (!$booking->isPending()) {
                    throw new Exception('Booking is not in pending status');
                }

                // Simulate payment processing
                $paymentResult = self::mockPaymentGateway($booking->total_amount);

                $payment = Payment::create([
                    'booking_id' => $bookingId,
                    'amount' => $booking->total_amount,
                    'status' => $paymentResult['success'] ? 'success' : 'failed',
                    'payment_method' => $paymentMethod,
                    'payment_details' => array_merge($paymentDetails, [
                        'processed_at' => now()->toDateTimeString(),
                        'gateway_response' => $paymentResult['message'],
                    ]),
                ]);

                if ($paymentResult['success']) {
                    $booking->confirm();

                    return [
                        'success' => true,
                        'message' => 'Payment processed successfully',
                        'data' => [
                            'payment' => $payment,
                            'booking' => $booking->fresh(),
                            'transaction_id' => $payment->transaction_id,
                        ],
                    ];
                }

                // If payment failed, keep booking in pending status
                return [
                    'success' => false,
                    'message' => 'Payment failed: ' . $paymentResult['message'],
                    'data' => [
                        'payment' => $payment,
                        'booking' => $booking,
                    ],
                ];
            });
        } catch (Exception $e) {
            Log::error('Payment processing failed: ' . $e->getMessage(), [
                'booking_id' => $bookingId,
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Payment processing failed: ' . $e->getMessage(),
                'data' => null,
            ];
        }
    }

    /**
     * Mock payment gateway - Simulates 90% success rate
     */
    private static function mockPaymentGateway(float $amount): array
    {
        // Simulate processing delay
        usleep(rand(100000, 500000)); // 0.1 to 0.5 seconds

        // 90% success rate
        $success = (rand(1, 100) <= 90);

        if ($success) {
            return [
                'success' => true,
                'message' => 'Payment completed successfully',
                'gateway_transaction_id' => 'GTW-' . strtoupper(bin2hex(random_bytes(8))),
            ];
        }

        $errorMessages = [
            'Insufficient funds',
            'Payment gateway timeout',
            'Invalid payment method',
            'Transaction declined by bank',
        ];

        return [
            'success' => false,
            'message' => $errorMessages[array_rand($errorMessages)],
            'gateway_transaction_id' => null,
        ];
    }

    /**
     * Refund payment
     */
    public static function refundPayment(int $paymentId, string $reason = 'Customer request'): array
    {
        try {
            return DB::transaction(function () use ($paymentId, $reason) {
                $payment = Payment::with('booking')->lockForUpdate()->findOrFail($paymentId);

                if ($payment->isRefunded()) {
                    throw new Exception('Payment is already refunded');
                }

                if (!$payment->isSuccessful()) {
                    throw new Exception('Only successful payments can be refunded');
                }

                // Simulate refund processing
                $refundResult = self::mockRefundGateway($payment->amount);

                if ($refundResult['success']) {
                    $payment->markAsRefunded();
                    $payment->update([
                        'payment_details' => array_merge($payment->payment_details ?? [], [
                            'refund_reason' => $reason,
                            'refunded_at' => now()->toDateTimeString(),
                            'refund_gateway_response' => $refundResult['message'],
                        ]),
                    ]);

                    // Cancel the booking
                    Booking::cancelBooking($payment->booking_id);

                    return [
                        'success' => true,
                        'message' => 'Payment refunded successfully',
                        'data' => [
                            'payment' => $payment->fresh(),
                            'refund_transaction_id' => $refundResult['refund_transaction_id'],
                        ],
                    ];
                }

                return [
                    'success' => false,
                    'message' => 'Refund failed: ' . $refundResult['message'],
                    'data' => null,
                ];
            });
        } catch (Exception $e) {
            Log::error('Refund processing failed: ' . $e->getMessage(), [
                'payment_id' => $paymentId,
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Refund processing failed: ' . $e->getMessage(),
                'data' => null,
            ];
        }
    }

    /**
     * Mock refund gateway - Simulates 95% success rate
     */
    private static function mockRefundGateway(float $amount): array
    {
        // Simulate processing delay
        usleep(rand(100000, 300000)); // 0.1 to 0.3 seconds

        // 95% success rate
        $success = (rand(1, 100) <= 95);

        if ($success) {
            return [
                'success' => true,
                'message' => 'Refund completed successfully',
                'refund_transaction_id' => 'RFD-' . strtoupper(bin2hex(random_bytes(8))),
            ];
        }

        return [
            'success' => false,
            'message' => 'Refund gateway timeout',
            'refund_transaction_id' => null,
        ];
    }

    /**
     * Get payment status
     */
    public static function getPaymentStatus(int $paymentId): array
    {
        try {
            $payment = Payment::with(['booking.ticket.event'])->findOrFail($paymentId);

            return [
                'success' => true,
                'message' => 'Payment details retrieved successfully',
                'data' => $payment,
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Payment not found',
                'data' => null,
            ];
        }
    }
}
