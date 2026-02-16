<?php

namespace App\Console\Commands;

use App\Models\Booking;
use Illuminate\Console\Command;

class FinishCompletedBookings extends Command
{
    protected $signature = 'bookings:finish';

    protected $description = 'Mark confirmed bookings as finished when their schedule end time has passed';

    public function handle()
    {
        $count = Booking::where('status', 'confirmed')
            ->whereHas('schedule', function ($query) {
                $query->where('end_time', '<', now());
            })
            ->update(['status' => 'finished']);

        $this->info("Marked {$count} booking(s) as finished.");

        return Command::SUCCESS;
    }
}
