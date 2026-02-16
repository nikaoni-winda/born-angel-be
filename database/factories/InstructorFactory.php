<?php

namespace Database\Factories;

use App\Models\Instructor;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class InstructorFactory extends Factory
{
    protected $model = Instructor::class;

    public function definition(): array
    {
        return [
            'service_id' => Service::factory(),
            'user_id' => User::factory()->state(['role' => 'instructor']),
            'bio' => fake()->paragraph(),
            'photo' => 'https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?q=80&w=800&auto=format&fit=crop',
        ];
    }
}
