<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Booking;
use App\Models\Schedule;
use App\Models\Instructor;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

class AdminDashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $isSuperAdmin = $user->role === 'super_admin';

        // 1. Basic Counts
        $totalUsers = User::where('role', 'user')->count();
        $totalInstructors = Instructor::count();
        $totalSchedules = Schedule::where('start_time', '>', now())->count();

        // 2. Booking Stats
        $totalBookings = Booking::count();
        $pendingBookings = Booking::where('status', 'pending')->count();
        $confirmedBookings = Booking::where('status', 'confirmed')->count();

        // 3. Revenue (Confirmed Only) - Super Admin Only
        $totalRevenue = $isSuperAdmin ? Booking::where('status', 'confirmed')->sum('total_price') : 0;

        // 4. Recent Transactions
        $recentBookings = Booking::with(['user', 'schedule.service', 'payment'])
            ->latest()
            ->take(5)
            ->get();

        // 5. Popular Services (Top 3) - Super Admin Only
        $popularServices = [];
        if ($isSuperAdmin) {
            $popularServices = DB::table('bookings')
                ->join('schedules', 'bookings.schedule_id', '=', 'schedules.id')
                ->join('services', 'schedules.service_id', '=', 'services.id')
                ->select('services.name', DB::raw('count(bookings.id) as total_bookings'))
                ->groupBy('services.id', 'services.name')
                ->orderBy('total_bookings', 'desc')
                ->take(3)
                ->get();
        }

        return response()->json([
            'stats' => [
                'total_users' => $totalUsers,
                'total_instructors' => $totalInstructors,
                'upcoming_classes' => $totalSchedules,
                'total_bookings' => $totalBookings,
                'pending_bookings' => $pendingBookings,
                'confirmed_bookings' => $confirmedBookings,
                'total_revenue' => $isSuperAdmin ? (float) $totalRevenue : null,
            ],
            'recent_bookings' => $recentBookings,
            'popular_services' => $isSuperAdmin ? $popularServices : []
        ]);
    }
}
