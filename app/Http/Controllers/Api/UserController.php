<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    // Admin & Super Admin: Get all users (with optional role filter)
    public function index(Request $request)
    {
        $query = User::query();

        // Optional filter: /api/users?role=instructor
        if ($request->has('role')) {
            $query->where('role', $request->role);
        }

        $users = $query->latest()->paginate($request->input('per_page', 15));
        return response()->json($users);
    }

    // Admin & Super Admin: Get single user
    public function show($id)
    {
        $user = User::findOrFail($id);
        return response()->json($user);
    }

    // Admin & Super Admin: Create new Account (Admin or Instructor)
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'phone_number' => 'required|string|unique:users',
            'role' => 'required|in:admin,super_admin,instructor', // Users must register themselves via AuthController
        ]);

        // Authorization Logic: Structure Hierarchy
        // 1. Only Super Admin can create 'super_admin'
        if ($request->role === 'super_admin' && $request->user()->role !== 'super_admin') {
            return response()->json(['message' => 'Unauthorized. Only Super Admin can create other Super Admins.'], 403);
        }

        // Note: Password hashing is handled by User Model casts
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => $request->password,
            'phone_number' => $request->phone_number,
            'role' => $request->role,
        ]);

        return response()->json($user, 201);
    }

    // Admin & Super Admin: Update User
    public function update(Request $request, $id)
    {
        $userToUpdate = User::findOrFail($id);

        // Security: Start of Hierarchy Checks

        // 1. Protect Master Account (ID 1)
        // No one can edit ID 1 unless it is ID 1 themselves updating their own profile
        if ($userToUpdate->id === 1 && $request->user()->id !== 1) {
            return response()->json(['message' => 'Cannot modify the Master Account.'], 403);
        }

        // 2. Only Super Admin can edit other Admins/Super Admins
        if (in_array($userToUpdate->role, ['admin', 'super_admin']) && $request->user()->id !== $userToUpdate->id) {
            if ($request->user()->role !== 'super_admin') {
                return response()->json(['message' => 'Unauthorized. Only Super Admin can manage other Admins.'], 403);
            }
        }

        // 3. Prevent Admin from promoting someone to Admin/Super Admin
        if ($request->has('role') && in_array($request->role, ['admin', 'super_admin'])) {
            if ($request->user()->role !== 'super_admin') {
                return response()->json(['message' => 'Unauthorized. Only Super Admin can promote users to Admin.'], 403);
            }
        }

        // End of Hierarchy Checks

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|string|email|max:255|unique:users,email,' . $id,
            'phone_number' => 'sometimes|string|unique:users,phone_number,' . $id,
            'role' => 'sometimes|in:admin,super_admin,instructor,user',
            'password' => 'sometimes|string|min:8',
        ]);

        // If password is present, model casts will handle hashing automatically
        $userToUpdate->update($validated);

        return response()->json($userToUpdate);
    }

    // Admin & Super Admin: Delete User
    public function destroy(Request $request, $id)
    {
        $user = User::findOrFail($id);

        // 1. Prevent self-deletion
        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'Cannot delete yourself.'], 400);
        }

        // 2. Protect Master Account (ID 1)
        if ($user->id === 1) {
            return response()->json(['message' => 'Cannot delete the Master Account.'], 403);
        }

        // 3. Only Super Admin can delete other Admins/Super Admins
        if (in_array($user->role, ['admin', 'super_admin'])) {
            if ($request->user()->role !== 'super_admin') {
                return response()->json(['message' => 'Unauthorized. Only Super Admin can delete Admins.'], 403);
            }
        }

        $user->delete();

        return response()->json(['message' => 'User deleted successfully']);
    }
}
