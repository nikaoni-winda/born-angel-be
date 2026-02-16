<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    // Show currrent user profile
    public function show(Request $request)
    {
        return response()->json($request->user());
    }

    // Update current user profile
    public function update(Request $request)
    {
        $user = $request->user();

        // Authorization Rule: Only 'user' and 'super_admin' can update profile.
        // 'admin' cannot update profile.
        if (in_array($user->role, ['admin'])) {
            return response()->json(['message' => 'Unauthorized: Admins cannot update profile.'], 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => [
                'sometimes',
                'string',
                'email',
                'max:255',
                Rule::unique('users')->ignore($user->id), // Ignore current user email check
            ],
            'phone_number' => [
                'sometimes',
                'string',
                Rule::unique('users')->ignore($user->id),
            ],
            'password' => 'sometimes|string|min:8',
        ]);

        if ($request->has('password')) {
            $validated['password'] = Hash::make($request->password);
        }

        $user->update($validated);

        return response()->json([
            'message' => 'Profile updated successfully.',
            'user' => $user
        ]);
    }

    // Delete account (Only for regular users)
    public function destroy(Request $request)
    {
        $user = $request->user();

        // Authorization: Only 'user' can delete their own account.
        // Super admin cannot self-delete here (safety), Admin cannot do anything.
        if ($user->role !== 'user') {
            return response()->json(['message' => 'Unauthorized: Only regular users can delete their account.'], 403);
        }

        // Revoke all tokens first (Logout from all devices)
        $user->tokens()->delete();

        // Delete user
        $user->delete();

        return response()->json(['message' => 'Account deleted successfully.']);
    }
}
