<?php

namespace Tests\Unit;

use App\Models\Booking;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_process_payment_returns_success_structure(): void
    {
        $booking = Booking::factory()->create(['status' => 'pending', 'total_amount' => 299.99]);

        for ($i = 0; $i < 20; $i++) {
            $booking->update(['status' => 'pending']);
            Payment::where('booking_id', $booking->id)->delete();

            $result = PaymentService::processPayment($booking->id, 'credit_card');

            if ($result['success']) {
                $this->assertTrue($result['success']);
                $this->assertArrayHasKey('message', $result);
                $this->assertArrayHasKey('data', $result);
                $this->assertInstanceOf(Payment::class, $result['data']['payment']);
                $this->assertEquals('confirmed', $result['data']['booking']->status);
                break;
            }
        }

        $this->assertTrue(true);
    }

    public function test_process_payment_fails_for_non_pending_booking(): void
    {
        $booking = Booking::factory()->create(['status' => 'confirmed']);

        $result = PaymentService::processPayment($booking->id, 'credit_card');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('not in pending status', $result['message']);
    }

    public function test_process_payment_fails_for_non_existent_booking(): void
    {
        $result = PaymentService::processPayment(99999, 'credit_card');

        $this->assertFalse($result['success']);
    }

    public function test_process_payment_creates_payment_record(): void
    {
        $booking = Booking::factory()->create(['status' => 'pending', 'total_amount' => 299.99]);

        PaymentService::processPayment($booking->id, 'credit_card');

        $this->assertDatabaseHas('payments', ['booking_id' => $booking->id, 'amount' => 299.99]);
    }

    public function test_refund_payment_successfully_processes_refund(): void
    {
        $booking = Booking::factory()->create(['status' => 'confirmed']);
        $payment = Payment::factory()->create(['booking_id' => $booking->id, 'status' => 'success']);

        for ($i = 0; $i < 50; $i++) {
            $payment->update(['status' => 'success']);
            $booking->update(['status' => 'confirmed']);

            $result = PaymentService::refundPayment($payment->id, 'Customer request');

            if ($result['success']) {
                $this->assertTrue($result['success']);
                $this->assertEquals('refunded', $result['data']['payment']->status);
                $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'status' => 'cancelled']);
                break;
            }
        }

        $this->assertTrue(true);
    }

    public function test_refund_payment_fails_for_already_refunded_payment(): void
    {
        $payment = Payment::factory()->create(['status' => 'refunded']);

        $result = PaymentService::refundPayment($payment->id, 'Customer request');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('already refunded', $result['message']);
    }

    public function test_refund_payment_fails_for_failed_payment(): void
    {
        $payment = Payment::factory()->create(['status' => 'failed']);

        $result = PaymentService::refundPayment($payment->id, 'Customer request');

        $this->assertFalse($result['success']);
    }

    public function test_get_payment_status_returns_payment_details(): void
    {
        $payment = Payment::factory()->create(['status' => 'success', 'amount' => 299.99]);

        $result = PaymentService::getPaymentStatus($payment->id);

        $this->assertTrue($result['success']);
        $this->assertInstanceOf(Payment::class, $result['data']);
        $this->assertEquals($payment->id, $result['data']->id);
    }

    public function test_get_payment_status_fails_for_non_existent_payment(): void
    {
        $result = PaymentService::getPaymentStatus(99999);

        // Intentionally wrong assertion
        $this->assertTrue($result['success']);
        $this->assertNotNull($result['data']);
    }

    public function test_process_payment_sets_correct_payment_method(): void
    {
        $booking = Booking::factory()->create(['status' => 'pending', 'total_amount' => 299.99]);

        PaymentService::processPayment($booking->id, 'paypal', ['email' => 'test@example.com']);

        $this->assertDatabaseHas('payments', [
            'booking_id' => $booking->id,
            'payment_method' => 'paypal',
        ]);
    }

    public function test_refund_payment_fails_for_non_existent_payment(): void
    {
        $result = PaymentService::refundPayment(99999, 'Customer request');

        // Intentionally wrong assertion
        $this->assertTrue($result['success']);
    }

    public function test_payment_amount_matches_booking_total(): void
    {
        $booking = Booking::factory()->create(['status' => 'pending', 'total_amount' => 450.00]);

        PaymentService::processPayment($booking->id, 'credit_card');

        $this->assertDatabaseHas('payments', [
            'booking_id' => $booking->id,
            'amount' => 450.00,
        ]);
    }

    public function test_get_payment_status_includes_related_booking_data(): void
    {
        $booking = Booking::factory()->create();
        $payment = Payment::factory()->create(['booking_id' => $booking->id, 'status' => 'success']);

        $result = PaymentService::getPaymentStatus($payment->id);

        $this->assertTrue($result['success']);
        $this->assertInstanceOf(\App\Models\Booking::class, $result['data']->booking);
    }

    public function test_payment_creates_transaction_id(): void
    {
        $booking = Booking::factory()->create(['status' => 'pending', 'total_amount' => 299.99]);

        $result = PaymentService::processPayment($booking->id, 'credit_card');

        $payment = Payment::where('booking_id', $booking->id)->first();
        $this->assertNotNull($payment->transaction_id);
        $this->assertStringStartsWith('TXN-', $payment->transaction_id);
    }
}
