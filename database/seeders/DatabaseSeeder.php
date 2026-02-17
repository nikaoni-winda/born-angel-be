<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Service;
use App\Models\Instructor;
use App\Models\Schedule;
use App\Models\Booking;
use App\Models\Review;
use App\Models\Payment;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Skip seeding if data already exists
        if (User::count() > 0) {
            $this->command->info('Database already seeded. Skipping.');
            return;
        }

        // 1. Create specific testing users
        $this->command->info('Creating testing users...');

        $superAdmin = User::firstOrCreate(
            ['email' => 'superadmin@example.com'],
            [
                'name' => 'Super Owner',
                'password' => Hash::make('password'),
                'phone_number' => '080000000000',
                'role' => 'super_admin',
            ]
        );

        $admin = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin Born Angel',
                'password' => Hash::make('password'),
                'phone_number' => '081111111111',
                'role' => 'admin',
            ]
        );

        $customer = User::firstOrCreate(
            ['email' => 'user@example.com'],
            [
                'name' => 'Regular Customer',
                'password' => Hash::make('password'),
                'phone_number' => '083333333333',
                'role' => 'user',
            ]
        );

        // 2. Create Services
        $this->command->info('Creating services...');
        $services = [
            [
                'name' => 'Douyin Makeup Look',
                'description' => 'Learn the viral Douyin makeup style focusing on large eyes and glossy lips. This course covers everything from skin prep to the final shimmering touches.',
                'price' => 350000,
                'duration_minutes' => 120,
                'image' => 'https://images.unsplash.com/photo-1522335789183-b15222bd2edf?q=80&w=800&auto=format&fit=crop',
            ],
            [
                'name' => 'Korean Glass Skin',
                'description' => 'Natural Korean makeup techniques to achieve a healthy, clear, and glowing skin look. Master the art of the perfect base and minimalist elegance.',
                'price' => 300000,
                'duration_minutes' => 90,
                'image' => 'https://images.unsplash.com/photo-1512496015851-a90fb38ba796?q=80&w=800&auto=format&fit=crop',
            ],
            [
                'name' => 'Bridal Glamour',
                'description' => 'Long-lasting and elegant bridal makeup course for the special day. Focus on durability, high-definition products, and timeless beauty.',
                'price' => 750000,
                'duration_minutes' => 180,
                'image' => 'https://images.unsplash.com/photo-1457974182554-a0d0ed8bcfe6?q=80&w=800&auto=format&fit=crop',
            ],
            [
                'name' => 'Signature Eye Glam',
                'description' => 'Master various eye makeup techniques from smokey eyes to cut crease. This intensive workshop focuses on precision and blending.',
                'price' => 250000,
                'duration_minutes' => 60,
                'image' => 'https://images.unsplash.com/photo-1487412947147-5cebf100ffc2?q=80&w=800&auto=format&fit=crop',
            ]
        ];

        $serviceModels = [];
        foreach ($services as $s) {
            $serviceModels[] = Service::create($s);
        }

        // 3. Create Instructors
        $this->command->info('Creating instructors...');
        $instructorNames = ['Angeline', 'Jessica', 'Sarah', 'Emily'];
        $instructorModels = [];

        foreach ($instructorNames as $index => $name) {
            $user = User::firstOrCreate(
                ['email' => strtolower($name) . '@example.com'],
                [
                    'name' => 'Instructor ' . $name,
                    'password' => Hash::make('password'),
                    'phone_number' => '082222222' . $index,
                    'role' => 'instructor',
                ]
            );

            $instructorModels[] = Instructor::create([
                'user_id' => $user->id,
                'service_id' => $serviceModels[array_rand($serviceModels)]->id,
                'bio' => 'Professional MUA with specialized experience in the beauty industry. Certified by top academies and passionate about sharing knowledge.',
                'photo' => 'https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?q=80&w=800&auto=format&fit=crop',
            ]);
        }

        // 4. Create Schedules
        $this->command->info('Creating schedules...');
        foreach ($instructorModels as $instructor) {
            // Create 3 schedules for each instructor in the next 14 days
            for ($i = 1; $i <= 3; $i++) {
                $start = now()->addDays(rand(1, 14))->setHour(rand(9, 16))->setMinute(0)->setSecond(0);
                $end = (clone $start)->addMinutes($instructor->service->duration_minutes);

                Schedule::create([
                    'service_id' => $instructor->service_id,
                    'instructor_id' => $instructor->id,
                    'start_time' => $start,
                    'end_time' => $end,
                    'total_capacity' => 5,
                    'remaining_slots' => rand(1, 5),
                ]);
            }
        }

        // 5. Create some Bookings & Payments for the test customer
        $this->command->info('Creating bookings and payments...');
        $allSchedules = Schedule::all();

        foreach ($allSchedules->random(3) as $schedule) {
            $booking = Booking::create([
                'user_id' => $customer->id,
                'schedule_id' => $schedule->id,
                'booking_code' => 'BA-' . strtoupper(Str::random(8)),
                'status' => 'confirmed',
                'total_price' => $schedule->service->price,
                'booking_date' => now()->subDays(rand(1, 10))->toDateString(),
            ]);

            Payment::create([
                'booking_id' => $booking->id,
                'transaction_id' => 'MID-' . Str::random(10),
                'payment_type' => 'credit_card',
                'gross_amount' => $booking->total_price,
                'transaction_status' => 'settlement',
                'fraud_status' => 'accept',
                'snap_token' => Str::random(32),
            ]);

            // Add a review for each confirmed booking
            Review::create([
                'booking_id' => $booking->id,
                'rating' => rand(4, 5),
                'comment' => fake()->randomElement([
                    'Perfect class! Learnt so much.',
                    'Amazing instructor and very clear explanations.',
                    'Value for money. The techniques are professional.',
                    'Very happy with the results. Will join again!',
                ]),
            ]);
        }

        $this->command->info('✅ Database Seeded Successfully!');
        $this->command->info('👤 Admin: admin@example.com (password)');
        $this->command->info('👤 Customer: user@example.com (password)');
    }
}