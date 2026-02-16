<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Models\Booking;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    // Public: All reviews for homepage testimonials section
    public function testimonials(Request $request)
    {
        $query = Review::with(['booking.user', 'booking.schedule.service', 'booking.schedule.instructor.user']);

        if ($request->has('instructor_id')) {
            $query->whereHas('booking.schedule', function ($q) use ($request) {
                $q->where('instructor_id', $request->instructor_id);
            });
        }

        $reviews = $query->latest()->paginate($request->input('per_page', 10));
        return response()->json($reviews);
    }

    // Authenticated: Get reviews (context-aware per role)
    public function index(Request $request)
    {
        $query = Review::with(['booking.user', 'booking.schedule.service', 'booking.schedule.instructor.user']);

        // Context 1: Logged-in User (OWN REVIEWS ONLY)
        if ($request->user() && $request->user()->role === 'user') {
            $query->whereHas('booking', function ($q) use ($request) {
                $q->where('user_id', $request->user()->id);
            });
        }
        // Context 2: Logged-in Instructor (RESTRICTED VIEW)
        elseif ($request->user() && $request->user()->role === 'instructor') {
            // Must have instructor profile
            if (!$request->user()->instructor) {
                return response()->json(['message' => 'Instructor profile not found.'], 404);
            }

            // Filter: booking -> schedule -> instructor_id MUST be Me
            $query->whereHas('booking.schedule', function ($q) use ($request) {
                $q->where('instructor_id', $request->user()->instructor->id);
            });
        }
        // Context 3: Public / Admin (ALL REVIEWS)
        else {
            // Optional Filter
            if ($request->has('instructor_id')) {
                $query->whereHas('booking.schedule', function ($q) use ($request) {
                    $q->where('instructor_id', $request->instructor_id);
                });
            }
        }

        $reviews = $query->latest()->paginate($request->input('per_page', 15));
        return response()->json($reviews);
    }

    // User: Create Review
    public function store(Request $request)
    {
        $request->validate([
            'booking_id' => 'required|exists:bookings,id',
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string',
        ]);

        $booking = Booking::with('schedule')->findOrFail($request->booking_id);

        // 1. Authorization: Must be own booking
        if ($booking->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized. This is not your booking.'], 403);
        }

        // 2. Class Must be Finished (Schedule End Time < Now)
        if ($booking->schedule->end_time > now()) {
            return response()->json(['message' => 'Cannot review a class that hasn\'t finished yet.'], 400);
        }

        // 3. Prevent double review
        if (Review::where('booking_id', $booking->id)->exists()) {
            return response()->json(['message' => 'You have already reviewed this booking.'], 400);
        }

        $review = Review::create([
            'booking_id' => $booking->id,
            'rating' => $request->rating,
            'comment' => $request->comment,
        ]);

        return response()->json($review, 201);
    }

    // User: Update own Review
    public function update(Request $request, $id)
    {
        $review = Review::findOrFail($id);

        // Check ownership via Booking relation
        $booking = Booking::findOrFail($review->booking_id);

        if ($booking->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'rating' => 'sometimes|integer|min:1|max:5',
            'comment' => 'sometimes|nullable|string',
        ]);

        $review->update($request->only('rating', 'comment'));

        return response()->json($review);
    }

    // User (Owner) or Admin/Super Admin: Delete Review
    public function destroy(Request $request, $id)
    {
        $review = Review::findOrFail($id);
        $booking = Booking::findOrFail($review->booking_id);

        // Authorization Logic
        $isOwner = $booking->user_id === $request->user()->id;
        $isAdmin = in_array($request->user()->role, ['admin', 'super_admin']);

        if (!$isOwner && !$isAdmin) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $review->delete();

        return response()->json(['message' => 'Review deleted successfully']);
    }
}
