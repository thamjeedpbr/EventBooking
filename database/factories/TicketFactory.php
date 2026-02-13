<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\Ticket;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Ticket>
 */
class TicketFactory extends Factory
{
    protected $model = Ticket::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = fake()->numberBetween(50, 200);
        $availableQuantity = fake()->numberBetween(0, $quantity);

        return [
            'event_id' => Event::inRandomOrder()->first()?->id ?? Event::factory()->create()->id,
            'type' => fake()->randomElement(['VIP', 'Regular', 'Early Bird', 'General Admission', 'Student']),
            'price' => fake()->randomFloat(2, 10, 500),
            'quantity' => $quantity,
            'available_quantity' => $availableQuantity,
        ];
    }

    /**
     * Indicate that the ticket is sold out.
     */
    public function soldOut(): static
    {
        return $this->state(fn (array $attributes) => [
            'available_quantity' => 0,
        ]);
    }

    /**
     * Indicate that the ticket has full availability.
     */
    public function available(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'available_quantity' => $attributes['quantity'],
            ];
        });
    }
}
