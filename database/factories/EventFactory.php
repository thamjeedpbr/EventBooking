<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Event>
 */
class EventFactory extends Factory
{
    protected $model = Event::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(3),
            'description' => fake()->paragraphs(3, true),
            'date' => fake()->dateTimeBetween('+1 week', '+6 months'),
            'location' => fake()->city() . ', ' . fake()->state(),
            'created_by' => User::where('role', 'organizer')->inRandomOrder()->first()?->id
                ?? User::factory()->organizer()->create()->id,
        ];
    }

    /**
     * Indicate that the event is in the past.
     */
    public function past(): static
    {
        return $this->state(fn (array $attributes) => [
            'date' => fake()->dateTimeBetween('-6 months', '-1 week'),
        ]);
    }

    /**
     * Indicate that the event is upcoming soon.
     */
    public function upcoming(): static
    {
        return $this->state(fn (array $attributes) => [
            'date' => fake()->dateTimeBetween('+1 day', '+1 week'),
        ]);
    }
}
