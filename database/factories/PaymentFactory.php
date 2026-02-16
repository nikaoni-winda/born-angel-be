<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory(),
            'transaction_id' => 'MID-' . Str::random(10),
            'payment_type' => fake()->randomElement(['credit_card', 'bank_transfer', 'gopay']),
            'gross_amount' => fake()->randomFloat(2, 50000, 500000),
            'transaction_status' => fake()->randomElement(['pending', 'settlement', 'expire', 'cancel']),
            'fraud_status' => 'accept',
            'snap_token' => Str::random(32),
        ];
    }
}
