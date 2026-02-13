<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $booking = Booking::inRandomOrder()->first() ?? Booking::factory()->create();

        return [
            'booking_id' => $booking->id,
            'amount' => $booking->total_amount,
            'status' => fake()->randomElement(['success', 'failed', 'refunded']),
            'payment_method' => fake()->randomElement(['credit_card', 'debit_card', 'paypal', 'mock']),
            'transaction_id' => 'TXN-' . strtoupper(Str::random(12)),
            'payment_details' => [
                'processed_at' => now()->toDateTimeString(),
                'gateway_response' => 'Payment completed successfully',
                'gateway_transaction_id' => 'GTW-' . strtoupper(bin2hex(random_bytes(8))),
            ],
        ];
    }

    /**
     * Indicate that the payment was successful.
     */
    public function successful(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'success',
        ]);
    }

    /**
     * Indicate that the payment failed.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'payment_details' => [
                'processed_at' => now()->toDateTimeString(),
                'gateway_response' => 'Payment declined by bank',
                'gateway_transaction_id' => null,
            ],
        ]);
    }

    /**
     * Indicate that the payment was refunded.
     */
    public function refunded(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'refunded',
            'payment_details' => [
                'processed_at' => now()->toDateTimeString(),
                'gateway_response' => 'Payment completed successfully',
                'gateway_transaction_id' => 'GTW-' . strtoupper(bin2hex(random_bytes(8))),
                'refund_reason' => 'Customer request',
                'refunded_at' => now()->toDateTimeString(),
                'refund_gateway_response' => 'Refund completed successfully',
            ],
        ]);
    }
}
