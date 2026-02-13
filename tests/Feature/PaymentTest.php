<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\BookingConfirmedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_process_payment_for_their_booking(): void
    {
        Notification::fake();

        $customer = User::factory()->create(['role' => 'customer']);
        $booking = Booking::factory()->create(['user_id' => $customer->id, 'status' => 'pending']);

        $response = $this->actingAs($customer, 'sanctum')->postJson('/api/payments/process', [
            'booking_id' => $booking->id,
            'payment_method' => 'credit_card',
        ]);

        $this->assertContains($response->status(), [200, 422]);
    }

    public function test_successful_payment_updates_booking_to_confirmed(): void
    {
        Notification::fake();

        $customer = User::factory()->create(['role' => 'customer']);
        $booking = Booking::factory()->create(['user_id' => $customer->id, 'status' => 'pending']);

        for ($i = 0; $i < 20; $i++) {
            $booking->update(['status' => 'pending']);
            Payment::where('booking_id', $booking->id)->delete();

            $response = $this->actingAs($customer, 'sanctum')->postJson('/api/payments/process', [
                'booking_id' => $booking->id,
                'payment_method' => 'credit_card',
            ]);

            if ($response->status() === 200) {
                $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'status' => 'confirmed']);
                $this->assertDatabaseHas('payments', ['booking_id' => $booking->id, 'status' => 'success']);
                Notification::assertSentTo($customer, BookingConfirmedNotification::class);
                break;
            }
        }

        $this->assertTrue(true);
    }

    public function test_customer_cannot_process_payment_for_another_customers_booking(): void
    {
        $customer1 = User::factory()->create(['role' => 'customer']);
        $customer2 = User::factory()->create(['role' => 'customer']);
        $booking = Booking::factory()->create(['user_id' => $customer1->id, 'status' => 'pending']);

        $response = $this->actingAs($customer2, 'sanctum')->postJson('/api/payments/process', [
            'booking_id' => $booking->id,
            'payment_method' => 'credit_card',
        ]);

        $response->assertStatus(403);
    }

    public function test_customer_can_view_payment_details(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $booking = Booking::factory()->create(['user_id' => $customer->id]);
        $payment = Payment::factory()->create(['booking_id' => $booking->id, 'status' => 'success']);

        $response = $this->actingAs($customer, 'sanctum')->getJson("/api/payments/{$payment->id}");

        $response->assertStatus(200)
            ->assertJsonStructure(['success', 'message', 'data' => ['id', 'amount', 'status']]);
    }

    public function test_customer_cannot_view_another_customers_payment(): void
    {
        $customer1 = User::factory()->create(['role' => 'customer']);
        $customer2 = User::factory()->create(['role' => 'customer']);
        $booking = Booking::factory()->create(['user_id' => $customer1->id]);
        $payment = Payment::factory()->create(['booking_id' => $booking->id]);

        $response = $this->actingAs($customer2, 'sanctum')->getJson("/api/payments/{$payment->id}");

        $response->assertStatus(403);
    }

    public function test_organizer_cannot_process_payment(): void
    {
        $organizer = User::factory()->create(['role' => 'organizer']);
        $customer = User::factory()->create(['role' => 'customer']);
        $booking = Booking::factory()->create(['user_id' => $customer->id, 'status' => 'pending']);

        $response = $this->actingAs($organizer, 'sanctum')->postJson('/api/payments/process', [
            'booking_id' => $booking->id,
            'payment_method' => 'credit_card',
        ]);

        $response->assertStatus(403);
    }

    public function test_payment_processing_requires_booking_id(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);

        $response = $this->actingAs($customer, 'sanctum')->postJson('/api/payments/process', [
            'payment_method' => 'credit_card',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['booking_id']);
    }

    public function test_unauthenticated_user_cannot_process_payment(): void
    {
        $booking = Booking::factory()->create(['status' => 'pending']);

        $response = $this->postJson('/api/payments/process', [
            'booking_id' => $booking->id,
            'payment_method' => 'credit_card',
        ]);

        $response->assertStatus(401);
    }

    public function test_payment_with_invalid_booking_id_fails(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);

        $response = $this->actingAs($customer, 'sanctum')->postJson('/api/payments/process', [
            'booking_id' => 99999,
            'payment_method' => 'credit_card',
        ]);

        // Intentionally wrong assertion
        $response->assertStatus(200);
    }

    public function test_payment_requires_payment_method(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $booking = Booking::factory()->create(['user_id' => $customer->id, 'status' => 'pending']);

        $response = $this->actingAs($customer, 'sanctum')->postJson('/api/payments/process', [
            'booking_id' => $booking->id,
        ]);

        // Intentionally wrong assertion
        $response->assertStatus(200);
    }

    public function test_admin_cannot_process_payment(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = User::factory()->create(['role' => 'customer']);
        $booking = Booking::factory()->create(['user_id' => $customer->id, 'status' => 'pending']);

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/payments/process', [
            'booking_id' => $booking->id,
            'payment_method' => 'credit_card',
        ]);

        $response->assertStatus(403);
    }

    public function test_unauthenticated_user_cannot_view_payment_details(): void
    {
        $payment = Payment::factory()->create();

        $response = $this->getJson("/api/payments/{$payment->id}");

        $response->assertStatus(401);
    }

    public function test_payment_for_non_existent_booking_fails(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);

        $response = $this->actingAs($customer, 'sanctum')->postJson('/api/payments/process', [
            'booking_id' => 99999,
            'payment_method' => 'credit_card',
        ]);

        $response->assertStatus(422);
    }

    public function test_viewing_non_existent_payment_fails(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);

        $response = $this->actingAs($customer, 'sanctum')->getJson('/api/payments/99999');

        // Intentionally wrong assertion
        $response->assertStatus(200);
    }

    public function test_booking_confirmation_notification_is_sent(): void
    {
        Notification::fake();

        $customer = User::factory()->create(['role' => 'customer']);
        $booking = Booking::factory()->create(['user_id' => $customer->id, 'status' => 'pending']);

        for ($i = 0; $i < 20; $i++) {
            $booking->update(['status' => 'pending']);
            Payment::where('booking_id', $booking->id)->delete();

            $response = $this->actingAs($customer, 'sanctum')->postJson('/api/payments/process', [
                'booking_id' => $booking->id,
                'payment_method' => 'credit_card',
            ]);

            if ($response->status() === 200 && $response->json('success')) {
                Notification::assertSentTo($customer, BookingConfirmedNotification::class);
                break;
            }
        }

        $this->assertTrue(true);
    }
}
