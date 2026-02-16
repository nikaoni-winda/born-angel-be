<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Instructor;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class InstructorController extends Controller
{
    // Public: Get all instructors
    public function index(Request $request)
    {
        // Eager load 'service' AND 'user' to get instructor name
        $instructors = Instructor::with(['service', 'user'])->paginate($request->input('per_page', 10));
        return response()->json($instructors);
    }

    // Public: Get single instructor
    public function show($id)
    {
        $instructor = Instructor::with(['service', 'user', 'schedules'])->findOrFail($id);
        return response()->json($instructor);
    }

    // Admin Only: Create Instructor
    // Admin Only: Create Instructor
    public function store(Request $request)
    {
        // Role check handled in Route Middleware

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6',
            'phone_number' => 'required|string|max:20', // Required by DB
            'service_id' => 'required|exists:services,id',
            'bio' => 'required|string',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:5120',
        ]);

        // 1. Create User Account
        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'phone_number' => $validated['phone_number'],
            'role' => 'instructor',
        ]);

        // 2. Handle Photo Upload
        $photoUrl = null;
        if ($request->hasFile('photo')) {
            try {
                $file = $request->file('photo');
                $result = cloudinary()->uploadApi()->upload($file->getRealPath(), [
                    'folder' => 'instructors',
                ]);
                $photoUrl = $result['secure_url'];
            } catch (\Exception $e) {
                \Log::error("Instructor Photo Upload Error: " . $e->getMessage());
                // We proceed without photo or fail? Let's fail gracefully or just log
            }
        }

        // 3. Create Instructor Profile
        $instructor = Instructor::create([
            'user_id' => $user->id,
            'service_id' => $validated['service_id'],
            'bio' => $validated['bio'],
            'photo' => $photoUrl,
        ]);

        return response()->json($instructor->load(['user', 'service']), 201);
    }

    // Admin Only: Update Instructor
    public function update(Request $request, $id)
    {
        $instructor = Instructor::findOrFail($id);
        $user = $instructor->user;

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|string|email|max:255|unique:users,email,' . $user->id,
            'phone_number' => 'sometimes|string|max:20',
            'service_id' => 'sometimes|exists:services,id',
            'bio' => 'sometimes|string',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:5120',
        ]);

        // 1. Update User Account
        if (isset($validated['name']))
            $user->name = $validated['name'];
        if (isset($validated['email']))
            $user->email = $validated['email'];
        if (isset($validated['phone_number']))
            $user->phone_number = $validated['phone_number'];
        if ($user->isDirty())
            $user->save();

        // 2. Handle Photo Upload
        if ($request->hasFile('photo')) {
            try {
                $file = $request->file('photo');
                $result = cloudinary()->uploadApi()->upload($file->getRealPath(), [
                    'folder' => 'instructors',
                ]);
                $instructor->photo = $result['secure_url'];
            } catch (\Exception $e) {
                \Log::error("Instructor Photo Update Error: " . $e->getMessage());
                return response()->json(['message' => 'Image upload failed.'], 500);
            }
        }

        // 3. Update Instructor Profile
        if (isset($validated['service_id']))
            $instructor->service_id = $validated['service_id'];
        if (isset($validated['bio']))
            $instructor->bio = $validated['bio'];

        $instructor->save();

        return response()->json($instructor->load(['user', 'service']));
    }

    // Admin Only: Delete Instructor
    public function destroy(Request $request, $id)
    {
        $instructor = Instructor::findOrFail($id);

        // Optional: Revert user role to 'user' upon deletion?
        // $instructor->user->update(['role' => 'user']);

        $instructor->delete();

        return response()->json(['message' => 'Instructor deleted successfully']);
    }
}
