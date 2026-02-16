<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Booking;
use App\Models\Schedule;
use App\Models\Payment;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;

class BookingController extends Controller
{
    // User: Get own bookings | Admin & Super Admin: Get all bookings
    public function index(Request $request)
    {
        $query = Booking::with(['user', 'schedule.service', 'schedule.instructor.user', 'payment', 'review']);

        if ($request->user()->role === 'user') {
            // Users can ONLY see their own bookings
            $query->where('user_id', $request->user()->id);
        } else {
            // Admins/SuperAdmins can filter by specific user
            if ($request->has('user_id')) {
                $query->where('user_id', $request->user_id);
            }
        }

        // Admins see all (or filtered), Users see theirs
        $bookings = $query->latest()->paginate($request->input('per_page', 15));

        return response()->json($bookings);
    }

    // User: Create Booking
    public function store(Request $request)
    {
        $request->validate([
            'schedule_id' => 'required|exists:schedules,id',
        ]);

        try {
            // Start transaction to ensure atomicity
            $result = DB::transaction(function () use ($request) {
                // LOCK the schedule row FOR UPDATE
                // This prevents race conditions where multiple users try to book the last slot simultaneously.
                // Other transactions will wait until this one commits or rolls back.
                $schedule = Schedule::with('service')
                    ->where('id', $request->schedule_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                // Check availability within the lock
                if ($schedule->remaining_slots <= 0) {
                    throw new \Exception('Class is full.');
                }

                // Prevent double booking for same active schedule
                // (Inside transaction ensures consistent read)
                $existingBooking = Booking::where('user_id', $request->user()->id)
                    ->where('schedule_id', $schedule->id)
                    ->where('status', '!=', 'cancelled')
                    ->exists();

                if ($existingBooking) {
                    throw new \Exception('You already booked this class.');
                }

                // 1. Decrement slots (Atomic update within transaction)
                $schedule->decrement('remaining_slots');

                // 2. Create Booking (Pending Payment)
                $booking = Booking::create([
                    'user_id' => $request->user()->id,
                    'schedule_id' => $schedule->id,
                    'booking_code' => 'BA-' . strtoupper(\Illuminate\Support\Str::random(8)),
                    'status' => 'pending',
                    'total_price' => $schedule->service->price,
                    'booking_date' => now(),
                ]);

                // 3. Create Initial Payment Record
                // set 'payment_type' to 'tbd' (To Be Determined) as User hasn't chosen method in Snap yet
                Payment::create([
                    'booking_id' => $booking->id,
                    'gross_amount' => $booking->total_price,
                    'transaction_status' => Payment::STATUS_PENDING,
                    'payment_type' => 'tbd',
                ]);

                return $booking;
            });

            return response()->json($result->load(['schedule.service', 'payment']), 201);

        } catch (\Exception $e) {
            // Handle ModelNotFoundException specifically if needed, though 'exists' validation catches most
            if ($e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
                return response()->json(['message' => 'Schedule not found.'], 404);
            }

            // Return 400 for business logic errors (Class full, Double booking)
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    // User/Admin/SuperAdmin: Cancel Booking
    public function cancel(Request $request, $id)
    {
        $booking = Booking::with('payment')->findOrFail($id);

        // Authorization: Admin, Super Admin, OR The User who owns the booking
        if (!in_array($request->user()->role, ['admin', 'super_admin']) && $booking->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($booking->status === 'cancelled') {
            return response()->json(['message' => 'Booking already cancelled.'], 400);
        }

        DB::transaction(function () use ($booking) {
            // Update Booking Status
            $booking->update(['status' => 'cancelled']);

            // Return Slot
            $booking->schedule->increment('remaining_slots');

            // Update Payment Status if exists
            if ($booking->payment) {
                $booking->payment->update([
                    'transaction_status' => Payment::STATUS_CANCEL
                ]);
            }
        });

        return response()->json(['message' => 'Booking cancelled successfully']);
    }
}
