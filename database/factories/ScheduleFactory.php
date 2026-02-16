<?php

namespace Database\Factories;

use App\Models\Instructor;
use App\Models\Schedule;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

class ScheduleFactory extends Factory
{
    protected $model = Schedule::class;

    public function definition(): array
    {
        $startTime = fake()->dateTimeBetween('now', '+1 month');
        $endTime = (clone $startTime)->modify('+2 hours');

        return [
            'service_id' => Service::factory(),
            'instructor_id' => Instructor::factory(),
            'start_time' => $startTime,
            'end_time' => $endTime,
            'total_capacity' => 10,
            'remaining_slots' => 10,
        ];
    }
}
