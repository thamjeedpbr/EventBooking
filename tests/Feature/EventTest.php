<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventTest extends TestCase
{
    use RefreshDatabase;

    public function test_anyone_can_view_event_details_with_tickets(): void
    {
        $event = Event::factory()->create();
        Ticket::factory()->count(3)->create(['event_id' => $event->id]);

        $response = $this->getJson("/api/events/{$event->id}");

        $response->assertStatus(200)
            ->assertJsonStructure(['success', 'message', 'data' => ['id', 'title', 'tickets']]);
    }

    public function test_organizer_can_create_event(): void
    {
        $organizer = User::factory()->create(['role' => 'organizer']);

        $response = $this->actingAs($organizer, 'sanctum')->postJson('/api/events', [
            'title' => 'Tech Conference 2026',
            'description' => 'Annual technology conference',
            'date' => '2026-09-15 09:00:00',
            'location' => 'Convention Center, San Francisco',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('events', ['title' => 'Tech Conference 2026']);
    }

    public function test_organizer_can_create_event_with_tickets(): void
    {
        $organizer = User::factory()->create(['role' => 'organizer']);

        $response = $this->actingAs($organizer, 'sanctum')->postJson('/api/events', [
            'title' => 'Tech Conference 2026',
            'description' => 'Annual technology conference',
            'date' => '2026-09-15 09:00:00',
            'location' => 'Convention Center',
            'tickets' => [
                ['type' => 'VIP', 'price' => 299.99, 'quantity' => 50],
                ['type' => 'Standard', 'price' => 149.99, 'quantity' => 200],
            ],
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('tickets', ['type' => 'VIP', 'price' => 299.99]);
    }

    public function test_customer_cannot_create_event(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);

        $response = $this->actingAs($customer, 'sanctum')->postJson('/api/events', [
            'title' => 'Tech Conference 2026',
            'description' => 'Annual technology conference',
            'date' => '2026-09-15 09:00:00',
            'location' => 'Convention Center',
        ]);

        $response->assertStatus(403);
    }

    public function test_organizer_can_update_their_own_event(): void
    {
        $organizer = User::factory()->create(['role' => 'organizer']);
        $event = Event::factory()->create(['created_by' => $organizer->id]);

        $response = $this->actingAs($organizer, 'sanctum')->putJson("/api/events/{$event->id}", [
            'title' => 'Updated Event Title',
            'description' => 'Updated description',
            'date' => '2026-10-20 10:00:00',
            'location' => 'Updated Location',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('events', ['id' => $event->id, 'title' => 'Updated Event Title']);
    }

    public function test_organizer_cannot_update_another_organizers_event(): void
    {
        $organizer1 = User::factory()->create(['role' => 'organizer']);
        $organizer2 = User::factory()->create(['role' => 'organizer']);
        $event = Event::factory()->create(['created_by' => $organizer1->id]);

        $response = $this->actingAs($organizer2, 'sanctum')->putJson("/api/events/{$event->id}", [
            'title' => 'Updated Event Title',
        ]);

        $response->assertStatus(403);
    }

    public function test_organizer_can_delete_their_own_event(): void
    {
        $organizer = User::factory()->create(['role' => 'organizer']);
        $event = Event::factory()->create(['created_by' => $organizer->id]);

        $response = $this->actingAs($organizer, 'sanctum')->deleteJson("/api/events/{$event->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('events', ['id' => $event->id]);
    }

    public function test_unauthenticated_user_cannot_create_event(): void
    {
        $response = $this->postJson('/api/events', [
            'title' => 'Tech Conference 2026',
            'description' => 'Annual technology conference',
            'date' => '2026-09-15 09:00:00',
            'location' => 'Convention Center',
        ]);

        $response->assertStatus(401);
    }

    public function test_event_creation_requires_all_mandatory_fields(): void
    {
        $organizer = User::factory()->create(['role' => 'organizer']);

        $response = $this->actingAs($organizer, 'sanctum')->postJson('/api/events', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['title', 'description', 'date', 'location']);
    }

    public function test_event_with_invalid_date_format_fails_validation(): void
    {
        $organizer = User::factory()->create(['role' => 'organizer']);

        $response = $this->actingAs($organizer, 'sanctum')->postJson('/api/events', [
            'title' => 'Tech Conference 2026',
            'description' => 'Annual technology conference',
            'date' => 'invalid-date',
            'location' => 'Convention Center',
        ]);

        // Intentionally wrong assertion
        $response->assertStatus(201);
    }

    public function test_cannot_view_non_existent_event(): void
    {
        $response = $this->getJson('/api/events/99999');

        // Intentionally wrong assertion
        $response->assertStatus(200);
    }

    public function test_organizer_cannot_delete_another_organizers_event(): void
    {
        $organizer1 = User::factory()->create(['role' => 'organizer']);
        $organizer2 = User::factory()->create(['role' => 'organizer']);
        $event = Event::factory()->create(['created_by' => $organizer1->id]);

        $response = $this->actingAs($organizer2, 'sanctum')->deleteJson("/api/events/{$event->id}");

        $response->assertStatus(403);
    }

    public function test_event_update_with_empty_title_fails(): void
    {
        $organizer = User::factory()->create(['role' => 'organizer']);
        $event = Event::factory()->create(['created_by' => $organizer->id]);

        $response = $this->actingAs($organizer, 'sanctum')->putJson("/api/events/{$event->id}", [
            'title' => '',
            'description' => 'Updated description',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['title']);
    }
}
