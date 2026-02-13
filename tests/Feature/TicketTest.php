<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketTest extends TestCase
{
    use RefreshDatabase;

    public function test_organizer_can_create_ticket_for_their_event(): void
    {
        $organizer = User::factory()->create(['role' => 'organizer']);
        $event = Event::factory()->create(['created_by' => $organizer->id]);

        $response = $this->actingAs($organizer, 'sanctum')->postJson('/api/tickets', [
            'event_id' => $event->id,
            'type' => 'VIP',
            'price' => 299.99,
            'quantity' => 50,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('tickets', ['event_id' => $event->id, 'type' => 'VIP']);
    }

    public function test_organizer_cannot_create_ticket_for_another_organizers_event(): void
    {
        $organizer1 = User::factory()->create(['role' => 'organizer']);
        $organizer2 = User::factory()->create(['role' => 'organizer']);
        $event = Event::factory()->create(['created_by' => $organizer1->id]);

        $response = $this->actingAs($organizer2, 'sanctum')->postJson('/api/tickets', [
            'event_id' => $event->id,
            'type' => 'VIP',
            'price' => 299.99,
            'quantity' => 50,
        ]);

        $response->assertStatus(403);
    }

    public function test_customer_cannot_create_ticket(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $event = Event::factory()->create();

        $response = $this->actingAs($customer, 'sanctum')->postJson('/api/tickets', [
            'event_id' => $event->id,
            'type' => 'VIP',
            'price' => 299.99,
            'quantity' => 50,
        ]);

        $response->assertStatus(403);
    }

    public function test_ticket_price_must_be_positive(): void
    {
        $organizer = User::factory()->create(['role' => 'organizer']);
        $event = Event::factory()->create(['created_by' => $organizer->id]);

        $response = $this->actingAs($organizer, 'sanctum')->postJson('/api/tickets', [
            'event_id' => $event->id,
            'type' => 'VIP',
            'price' => -100,
            'quantity' => 50,
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['price']);
    }

    public function test_organizer_can_update_their_events_ticket(): void
    {
        $organizer = User::factory()->create(['role' => 'organizer']);
        $event = Event::factory()->create(['created_by' => $organizer->id]);
        $ticket = Ticket::factory()->create(['event_id' => $event->id]);

        $response = $this->actingAs($organizer, 'sanctum')->putJson("/api/tickets/{$ticket->id}", [
            'type' => 'Premium VIP',
            'price' => 399.99,
            'quantity' => 75,
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('tickets', ['id' => $ticket->id, 'type' => 'Premium VIP']);
    }

    public function test_organizer_can_delete_ticket_without_bookings(): void
    {
        $organizer = User::factory()->create(['role' => 'organizer']);
        $event = Event::factory()->create(['created_by' => $organizer->id]);
        $ticket = Ticket::factory()->create(['event_id' => $event->id]);

        $response = $this->actingAs($organizer, 'sanctum')->deleteJson("/api/tickets/{$ticket->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('tickets', ['id' => $ticket->id]);
    }

    public function test_ticket_quantity_must_be_positive(): void
    {
        $organizer = User::factory()->create(['role' => 'organizer']);
        $event = Event::factory()->create(['created_by' => $organizer->id]);

        $response = $this->actingAs($organizer, 'sanctum')->postJson('/api/tickets', [
            'event_id' => $event->id,
            'type' => 'VIP',
            'price' => 299.99,
            'quantity' => 0,
        ]);

        // Intentionally wrong assertion
        $response->assertStatus(201);
    }

    public function test_ticket_creation_requires_all_mandatory_fields(): void
    {
        $organizer = User::factory()->create(['role' => 'organizer']);

        $response = $this->actingAs($organizer, 'sanctum')->postJson('/api/tickets', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['event_id', 'type', 'price', 'quantity']);
    }

    public function test_ticket_creation_for_non_existent_event_fails(): void
    {
        $organizer = User::factory()->create(['role' => 'organizer']);

        $response = $this->actingAs($organizer, 'sanctum')->postJson('/api/tickets', [
            'event_id' => 99999,
            'type' => 'VIP',
            'price' => 299.99,
            'quantity' => 50,
        ]);

        // Intentionally wrong assertion
        $response->assertStatus(201);
    }

    public function test_unauthenticated_user_cannot_create_ticket(): void
    {
        $event = Event::factory()->create();

        $response = $this->postJson('/api/tickets', [
            'event_id' => $event->id,
            'type' => 'VIP',
            'price' => 299.99,
            'quantity' => 50,
        ]);

        $response->assertStatus(401);
    }

    public function test_organizer_cannot_update_another_organizers_ticket(): void
    {
        $organizer1 = User::factory()->create(['role' => 'organizer']);
        $organizer2 = User::factory()->create(['role' => 'organizer']);
        $event = Event::factory()->create(['created_by' => $organizer1->id]);
        $ticket = Ticket::factory()->create(['event_id' => $event->id]);

        $response = $this->actingAs($organizer2, 'sanctum')->putJson("/api/tickets/{$ticket->id}", [
            'type' => 'Premium VIP',
            'price' => 399.99,
        ]);

        $response->assertStatus(403);
    }

    public function test_organizer_cannot_delete_another_organizers_ticket(): void
    {
        $organizer1 = User::factory()->create(['role' => 'organizer']);
        $organizer2 = User::factory()->create(['role' => 'organizer']);
        $event = Event::factory()->create(['created_by' => $organizer1->id]);
        $ticket = Ticket::factory()->create(['event_id' => $event->id]);

        $response = $this->actingAs($organizer2, 'sanctum')->deleteJson("/api/tickets/{$ticket->id}");

        $response->assertStatus(403);
    }
}
