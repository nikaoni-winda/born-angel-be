<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Schedule;
use App\Models\Instructor;
use Illuminate\Validation\ValidationException;

class ScheduleController extends Controller
{
    // Public: Get schedules (Context-Aware)
    public function index(Request $request)
    {
        $query = Schedule::with(['service', 'instructor.user'])
            ->orderBy('start_time');

        // Context 1: Logged-in Instructor (RESTRICTED VIEW)
        if ($request->user() && $request->user()->role === 'instructor') {
            // Can ONLY see own schedules (Past, Present, Future)
            $query->where('instructor_id', $request->user()->instructor->id);
        }
        // Context 2: Admin / Super Admin (GOD VIEW)
        elseif ($request->user() && in_array($request->user()->role, ['admin', 'super_admin'])) {
            // Can see EVERYTHING (Past & Future). No time filter applied.
            // Still allows filtering by specific instructor if needed
            if ($request->has('instructor_id')) {
                $query->where('instructor_id', $request->instructor_id);
            }
        }
        // Context 3: Public / User (LIMITED VIEW)
        else {
            // Default: Show Only Upcoming
            $query->where('start_time', '>', now());

            // Optional Filter (Public/User can filter by instructor)
            if ($request->has('instructor_id')) {
                $query->where('instructor_id', $request->instructor_id);
            }
        }

        $schedules = $query->paginate($request->input('per_page', 15));

        return response()->json($schedules);
    }

    // Public: Get single schedule
    public function show($id)
    {
        $schedule = Schedule::with(['service', 'instructor.user'])->findOrFail($id);
        return response()->json($schedule);
    }

    // Admin Only: Create Schedule
    public function store(Request $request)
    {
        // Role check handled in Route Middleware

        // Accept 'capacity' as alias for 'total_capacity' from FE
        if ($request->has('capacity') && !$request->has('total_capacity')) {
            $request->merge(['total_capacity' => $request->capacity]);
        }

        $validated = $request->validate([
            'service_id' => 'required|exists:services,id',
            'instructor_id' => 'required|exists:instructors,id',
            'start_time' => 'required|date|after:now',
            'end_time' => 'required|date|after:start_time',
            'total_capacity' => 'required|integer|min:1',
        ]);

        // 1. Check if instructor teaches this service
        $instructor = Instructor::findOrFail($request->instructor_id);
        if ($instructor->service_id != $request->service_id) {
            throw ValidationException::withMessages([
                'instructor_id' => ['This instructor does not teach the selected service.'],
            ]);
        }

        // 2. Prevent Double Booking (Overlap Check)
        $hasConflict = Schedule::where('instructor_id', $request->instructor_id)
            ->where(function ($query) use ($request) {
                $query->whereBetween('start_time', [$request->start_time, $request->end_time])
                    ->orWhereBetween('end_time', [$request->start_time, $request->end_time])
                    ->orWhere(function ($q) use ($request) {
                        $q->where('start_time', '<', $request->start_time)
                            ->where('end_time', '>', $request->end_time);
                    });
            })
            ->exists();

        if ($hasConflict) {
            throw ValidationException::withMessages([
                'start_time' => ['Instructor is already booked for another schedule at this time.'],
            ]);
        }

        $validated['remaining_slots'] = $validated['total_capacity'];

        $schedule = Schedule::create($validated);

        return response()->json($schedule->load(['service', 'instructor.user']), 201);
    }

    // Admin Only: Update Schedule
    public function update(Request $request, $id)
    {
        $schedule = Schedule::findOrFail($id);

        $validated = $request->validate([
            'start_time' => 'sometimes|date|after:now',
            'end_time' => 'sometimes|date|after:start_time',
            'total_capacity' => 'sometimes|integer|min:1',
            'instructor_id' => 'sometimes|exists:instructors,id',
        ]);

        // 1. Logic Overlap Check (If time or instructor changed)
        if ($request->has('start_time') || $request->has('end_time') || $request->has('instructor_id')) {
            $startTime = $request->input('start_time', $schedule->start_time);
            $endTime = $request->input('end_time', $schedule->end_time);
            $instructorId = $request->input('instructor_id', $schedule->instructor_id);

            $hasConflict = Schedule::where('instructor_id', $instructorId)
                ->where('id', '!=', $id) // Exclude self
                ->where(function ($query) use ($startTime, $endTime) {
                    $query->whereBetween('start_time', [$startTime, $endTime])
                        ->orWhereBetween('end_time', [$startTime, $endTime])
                        ->orWhere(function ($q) use ($startTime, $endTime) {
                            $q->where('start_time', '<', $startTime)
                                ->where('end_time', '>', $endTime);
                        });
                })
                ->exists();

            if ($hasConflict) {
                throw ValidationException::withMessages([
                    'start_time' => ['Instructor is already booked for another schedule at this time.'],
                ]);
            }
        }

        // 2. Logic Capacity Update
        if ($request->has('total_capacity')) {
            $bookedCount = $schedule->total_capacity - $schedule->remaining_slots;
            // New capacity must be at least equal to current booking count
            if ($request->total_capacity < $bookedCount) {
                return response()->json(['message' => 'Cannot reduce capacity below current active bookings (' . $bookedCount . ').'], 400);
            }

            // Recalculate remaining slots
            $validated['remaining_slots'] = $request->total_capacity - $bookedCount;
        }

        $schedule->update($validated);

        return response()->json($schedule->load(['service', 'instructor.user']));
    }

    // Admin Only: Delete Schedule
    public function destroy(Request $request, $id)
    {
        $schedule = Schedule::findOrFail($id);

        // Prevent deletion if there are active bookings (exclude cancelled)
        if ($schedule->bookings()->where('status', '!=', 'cancelled')->count() > 0) {
            return response()->json(['message' => 'Cannot delete schedule with active bookings. Cancel bookings first.'], 400);
        }

        $schedule->delete();

        return response()->json(['message' => 'Schedule deleted successfully']);
    }
}
