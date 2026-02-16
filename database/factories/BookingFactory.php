<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class BookingFactory extends Factory
{
    protected $model = Booking::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'schedule_id' => Schedule::factory(),
            'booking_code' => 'BA-' . strtoupper(Str::random(8)),
            'status' => fake()->randomElement(['pending', 'confirmed', 'cancelled']),
            'total_price' => fake()->randomFloat(2, 50000, 500000),
            'booking_date' => fake()->date(),
        ];
    }
}
