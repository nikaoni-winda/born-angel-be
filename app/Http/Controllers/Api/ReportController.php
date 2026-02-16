<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Booking;
use App\Models\Service;
use App\Models\User;
use App\Models\Schedule;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ReportController extends Controller
{
    /**
     * Get Revenue Growth Data (Line Chart)
     * Period: weekly, monthly, yearly
     */
    public function revenue(Request $request)
    {
        $period = $request->query('period', 'monthly'); // weekly, monthly, yearly
        $query = Booking::where('status', 'confirmed')->join('payments', 'bookings.id', '=', 'payments.booking_id');

        if ($period === 'weekly') {
            // Last 7 days
            $query->selectRaw('DATE(bookings.booking_date) as date, SUM(bookings.total_price) as total_revenue')
                ->where('bookings.booking_date', '>=', Carbon::now()->subDays(7))
                ->groupBy('date')
                ->orderBy('date');
        } elseif ($period === 'yearly') {
            // Last 5 years
            $query->selectRaw('YEAR(bookings.booking_date) as date, SUM(bookings.total_price) as total_revenue')
                ->where('bookings.booking_date', '>=', Carbon::now()->subYears(5))
                ->groupBy('date')
                ->orderBy('date');
        } else {
            // Default: Monthly (Last 12 months)
            $query->selectRaw('DATE_FORMAT(bookings.booking_date, "%Y-%m") as date, SUM(bookings.total_price) as total_revenue')
                ->where('bookings.booking_date', '>=', Carbon::now()->subMonths(12))
                ->groupBy('date')
                ->orderBy('date');
        }

        $data = $query->get()->map(function ($item) use ($period) {
            $dateLabel = $item->date;
            if ($period === 'monthly') {
                $dateLabel = Carbon::createFromFormat('Y-m', $item->date)->format('M Y');
            } elseif ($period === 'weekly') {
                $dateLabel = Carbon::createFromFormat('Y-m-d', $item->date)->format('D, d M');
            }
            return [
                'name' => $dateLabel,
                'revenue' => (float) $item->total_revenue
            ];
        });

        // Calculate Totals
        $totalRevenue = Booking::where('status', 'confirmed')->sum('total_price');

        return response()->json([
            'chartData' => $data,
            'totalRevenue' => $totalRevenue
        ]);
    }

    /**
     * Get Service Performance (Pie/Bar Chart)
     * Shows: Revenue share by service, and total booking count
     */
    public function servicePerformance(Request $request)
    {
        // Revenue by Service Category
        $performance = DB::table('bookings')
            ->join('schedules', 'bookings.schedule_id', '=', 'schedules.id')
            ->join('services', 'schedules.service_id', '=', 'services.id')
            ->where('bookings.status', 'confirmed')
            ->selectRaw('services.name, SUM(bookings.total_price) as value, COUNT(bookings.id) as bookings_count')
            ->groupBy('services.name')
            ->orderByDesc('value')
            ->get();

        return response()->json($performance);
    }

    /**
     * Operational Stats (KPI Cards)
     */
    public function operationalStats(Request $request)
    {
        $today = Carbon::today();

        // 1. Occupancy Rate (Global Average)
        // (Total Booked Seats / Total Capacity) * 100 for finished schedules
        $schedules = Schedule::where('end_time', '<', now())->get();
        $totalCapacity = $schedules->sum('total_capacity');
        $totalBooked = $totalCapacity - $schedules->sum('remaining_slots');

        $occupancyRate = $totalCapacity > 0 ? round(($totalBooked / $totalCapacity) * 100, 1) : 0;

        // 2. Cancellation Rate
        $totalBookings = Booking::count();
        $cancelledBookings = Booking::where('status', 'cancelled')->count();
        $cancellationRate = $totalBookings > 0 ? round(($cancelledBookings / $totalBookings) * 100, 1) : 0;

        return response()->json([
            'occupancyRate' => $occupancyRate,
            'cancellationRate' => $cancellationRate,
            'totalInstructors' => User::where('role', 'instructor')->count(),
            'totalUsers' => User::where('role', 'user')->count(),
            'activeClassesToday' => Schedule::whereDate('start_time', $today)->count()
        ]);
    }

    /**
     * Get Instructor Performance Data (Bar Chart / Leaderboard)
     * Shows: Revenue generated, total bookings, classes taught, avg occupancy per instructor
     */
    public function instructorPerformance(Request $request)
    {
        $instructors = DB::table('instructors')
            ->join('users', 'instructors.user_id', '=', 'users.id')
            ->leftJoin('schedules', 'instructors.id', '=', 'schedules.instructor_id')
            ->leftJoin('bookings', function ($join) {
                $join->on('schedules.id', '=', 'bookings.schedule_id')
                    ->where('bookings.status', '=', 'confirmed');
            })
            ->whereNull('instructors.deleted_at')
            ->selectRaw("
                instructors.id,
                users.name as instructor_name,
                COUNT(DISTINCT schedules.id) as total_classes,
                COUNT(bookings.id) as total_bookings,
                COALESCE(SUM(bookings.total_price), 0) as total_revenue
            ")
            ->groupBy('instructors.id', 'users.name')
            ->orderByDesc('total_revenue')
            ->get();

        // Calculate occupancy rate per instructor
        $result = $instructors->map(function ($instructor) {
            $schedules = Schedule::where('instructor_id', $instructor->id)->get();
            $totalCapacity = $schedules->sum('total_capacity');
            $totalBooked = $totalCapacity - $schedules->sum('remaining_slots');
            $occupancyRate = $totalCapacity > 0
                ? round(($totalBooked / $totalCapacity) * 100, 1)
                : 0;

            return [
                'id' => $instructor->id,
                'name' => $instructor->instructor_name,
                'totalClasses' => (int) $instructor->total_classes,
                'totalBookings' => (int) $instructor->total_bookings,
                'totalRevenue' => (float) $instructor->total_revenue,
                'occupancyRate' => $occupancyRate,
            ];
        });

        return response()->json($result);
    }

    /**
     * Get Peak Hours / Weekly Traffic Trends
     * Shows: Total bookings per day of the week (Mon-Sun)
     */
    public function peakHours(Request $request)
    {
        // MySQL uses 1=Sunday, 2=Monday, ..., 7=Saturday
        // We want to map this to: Monday, Tuesday, ..., Sunday
        $traffic = DB::table('bookings')
            ->join('schedules', 'bookings.schedule_id', '=', 'schedules.id')
            ->where('bookings.status', 'confirmed')
            ->selectRaw('DAYOFWEEK(schedules.start_time) as day_num, COUNT(bookings.id) as total_bookings')
            ->groupBy('day_num')
            ->orderBy('day_num')
            ->get();

        // 1=Sun, 2=Mon, 3=Tue, 4=Wed, 5=Thu, 6=Fri, 7=Sat
        $daysMap = [
            1 => 'Sun',
            2 => 'Mon',
            3 => 'Tue',
            4 => 'Wed',
            5 => 'Thu',
            6 => 'Fri',
            7 => 'Sat'
        ];

        // Ensure all days are present, even if 0 bookings
        $result = [];
        // Reorder to start from Mon (2) to Sun (1)
        $order = [2, 3, 4, 5, 6, 7, 1];

        foreach ($order as $dayNum) {
            $found = $traffic->firstWhere('day_num', $dayNum);
            $result[] = [
                'day' => $daysMap[$dayNum],
                'bookings' => $found ? (int) $found->total_bookings : 0
            ];
        }

        return response()->json($result);
    }
}
