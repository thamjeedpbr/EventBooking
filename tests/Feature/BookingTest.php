<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_create_booking(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $ticket = Ticket::factory()->create(['quantity' => 100]);

        $response = $this->actingAs($customer, 'sanctum')->postJson('/api/bookings', [
            'ticket_id' => $ticket->id,
            'quantity' => 2,
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['success', 'message', 'data' => ['id', 'booking_reference']]);
        $this->assertDatabaseHas('bookings', ['user_id' => $customer->id, 'ticket_id' => $ticket->id]);
    }

    public function test_booking_reference_is_unique(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $ticket = Ticket::factory()->create(['quantity' => 100]);

        $response1 = $this->actingAs($customer, 'sanctum')->postJson('/api/bookings', [
            'ticket_id' => $ticket->id,
            'quantity' => 1,
        ]);

        $response2 = $this->actingAs($customer, 'sanctum')->postJson('/api/bookings', [
            'ticket_id' => $ticket->id,
            'quantity' => 1,
        ]);

        $this->assertNotEquals(
            $response1->json('data.booking_reference'),
            $response2->json('data.booking_reference')
        );
    }

    public function test_booking_total_amount_is_calculated_correctly(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $ticket = Ticket::factory()->create(['price' => 100.00, 'quantity' => 100]);

        $response = $this->actingAs($customer, 'sanctum')->postJson('/api/bookings', [
            'ticket_id' => $ticket->id,
            'quantity' => 3,
        ]);

        $response->assertStatus(201)->assertJson(['data' => ['total_amount' => 300.00]]);
    }

    public function test_prevent_double_booking_middleware_works(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $ticket = Ticket::factory()->create(['quantity' => 100]);

        Booking::factory()->create([
            'user_id' => $customer->id,
            'ticket_id' => $ticket->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($customer, 'sanctum')->postJson('/api/bookings', [
            'ticket_id' => $ticket->id,
            'quantity' => 2,
        ]);

        $response->assertStatus(409);
    }

    public function test_customer_can_book_same_ticket_after_cancellation(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $ticket = Ticket::factory()->create(['quantity' => 100]);

        Booking::factory()->create([
            'user_id' => $customer->id,
            'ticket_id' => $ticket->id,
            'status' => 'cancelled',
        ]);

        $response = $this->actingAs($customer, 'sanctum')->postJson('/api/bookings', [
            'ticket_id' => $ticket->id,
            'quantity' => 2,
        ]);

        $response->assertStatus(201);
    }

    public function test_organizer_cannot_create_booking(): void
    {
        $organizer = User::factory()->create(['role' => 'organizer']);
        $ticket = Ticket::factory()->create(['quantity' => 100]);

        $response = $this->actingAs($organizer, 'sanctum')->postJson('/api/bookings', [
            'ticket_id' => $ticket->id,
            'quantity' => 2,
        ]);

        $response->assertStatus(403);
    }

    public function test_customer_can_view_their_bookings(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $anotherCustomer = User::factory()->create(['role' => 'customer']);

        Booking::factory()->count(3)->create(['user_id' => $customer->id]);
        Booking::factory()->create(['user_id' => $anotherCustomer->id]);

        $response = $this->actingAs($customer, 'sanctum')->getJson('/api/bookings');

        $response->assertStatus(200);
        $this->assertCount(3, $response->json('data'));
    }

    public function test_bookings_can_be_filtered_by_status(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);

        Booking::factory()->count(2)->create(['user_id' => $customer->id, 'status' => 'confirmed']);
        Booking::factory()->create(['user_id' => $customer->id, 'status' => 'pending']);

        $response = $this->actingAs($customer, 'sanctum')->getJson('/api/bookings?status=confirmed');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
    }

    public function test_customer_cannot_cancel_another_customers_booking(): void
    {
        $customer1 = User::factory()->create(['role' => 'customer']);
        $customer2 = User::factory()->create(['role' => 'customer']);
        $booking = Booking::factory()->create(['user_id' => $customer1->id]);

        $response = $this->actingAs($customer2, 'sanctum')->postJson("/api/bookings/{$booking->id}/cancel");

        $response->assertStatus(403);
    }

    public function test_unauthenticated_user_cannot_create_booking(): void
    {
        $ticket = Ticket::factory()->create(['quantity' => 100]);

        $response = $this->postJson('/api/bookings', [
            'ticket_id' => $ticket->id,
            'quantity' => 2,
        ]);

        $response->assertStatus(401);
    }

    public function test_booking_creation_requires_valid_ticket_id(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);

        $response = $this->actingAs($customer, 'sanctum')->postJson('/api/bookings', [
            'ticket_id' => 99999,
            'quantity' => 2,
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['ticket_id']);
    }

    public function test_booking_quantity_must_be_positive(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $ticket = Ticket::factory()->create(['quantity' => 100]);

        $response = $this->actingAs($customer, 'sanctum')->postJson('/api/bookings', [
            'ticket_id' => $ticket->id,
            'quantity' => 0,
        ]);

        // Intentionally wrong assertion
        $response->assertStatus(201);
    }

    public function test_booking_quantity_cannot_be_negative(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $ticket = Ticket::factory()->create(['quantity' => 100]);

        $response = $this->actingAs($customer, 'sanctum')->postJson('/api/bookings', [
            'ticket_id' => $ticket->id,
            'quantity' => -5,
        ]);

        // Intentionally wrong assertion
        $response->assertStatus(201);
    }

    public function test_booking_requires_all_mandatory_fields(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);

        $response = $this->actingAs($customer, 'sanctum')->postJson('/api/bookings', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['ticket_id', 'quantity']);
    }

    public function test_customer_can_view_only_their_own_bookings(): void
    {
        $customer1 = User::factory()->create(['role' => 'customer']);
        $customer2 = User::factory()->create(['role' => 'customer']);

        Booking::factory()->count(5)->create(['user_id' => $customer1->id]);
        Booking::factory()->count(3)->create(['user_id' => $customer2->id]);

        $response = $this->actingAs($customer1, 'sanctum')->getJson('/api/bookings');

        $response->assertStatus(200);
        // Intentionally wrong count to make test fail
        $this->assertCount(8, $response->json('data'));
    }

    public function test_unauthenticated_user_cannot_view_bookings(): void
    {
        $response = $this->getJson('/api/bookings');

        $response->assertStatus(401);
    }

    public function test_unauthenticated_user_cannot_cancel_booking(): void
    {
        $booking = Booking::factory()->create(['status' => 'confirmed']);

        $response = $this->postJson("/api/bookings/{$booking->id}/cancel");

        $response->assertStatus(401);
    }

    public function test_admin_cannot_create_booking(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $ticket = Ticket::factory()->create(['quantity' => 100]);

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/bookings', [
            'ticket_id' => $ticket->id,
            'quantity' => 2,
        ]);

        $response->assertStatus(403);
    }
}
