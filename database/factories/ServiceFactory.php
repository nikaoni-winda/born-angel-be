<?php

namespace Database\Factories;

use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

class ServiceFactory extends Factory
{
    protected $model = Service::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'description' => fake()->sentence(20),
            'price' => fake()->randomFloat(2, 50000, 500000),
            'duration_minutes' => fake()->randomElement([30, 60, 90, 120]),
            'image' => 'https://images.unsplash.com/photo-1522335789183-b15222bd2edf?q=80&w=800&auto=format&fit=crop',
        ];
    }
}
