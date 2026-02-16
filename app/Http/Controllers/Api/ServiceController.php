<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Service;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    // Public: Get all services
    public function index(Request $request)
    {
        $services = Service::with('instructors')->paginate($request->input('per_page', 10));
        return response()->json($services);
    }

    // Public: Get single service
    public function show($id)
    {
        $service = Service::with(['instructors', 'schedules'])->findOrFail($id);
        return response()->json($service);
    }

    // Admin Only: Create Service
    public function store(Request $request)
    {
        // Validate input
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'price' => 'required|numeric',
            'duration_minutes' => 'required|integer',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:5120', // Max 5MB
        ]);

        // Handle Image Upload
        if ($request->hasFile('image')) {
            try {
                $file = $request->file('image');
                if (!$file->isValid()) {
                    throw new \Exception("Invalid file upload");
                }

                // Upload to Cloudinary using SDK v2
                $result = cloudinary()->uploadApi()->upload($file->getRealPath(), [
                    'folder' => 'services'
                ]);
                $validated['image'] = $result['secure_url'];
            } catch (\Exception $e) {
                \Log::error("Cloudinary Upload Error: " . $e->getMessage());
                // Also log trace
                \Log::error($e->getTraceAsString());
                return response()->json(['message' => 'Image upload failed. Check logs.'], 500);
            }
        }

        $service = Service::create($validated);

        return response()->json($service, 201);
    }

    // Admin Only: Update Service
    public function update(Request $request, $id)
    {
        $service = Service::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'price' => 'sometimes|numeric',
            'duration_minutes' => 'sometimes|integer',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:5120', // Max 5MB
        ]);

        if ($request->hasFile('image')) {
            try {
                $file = $request->file('image');
                if (!$file->isValid()) {
                    throw new \Exception("Invalid file upload");
                }
                // Upload to Cloudinary using SDK v2
                $result = cloudinary()->uploadApi()->upload($file->getRealPath(), [
                    'folder' => 'services'
                ]);
                $validated['image'] = $result['secure_url'];
            } catch (\Exception $e) {
                \Log::error("Cloudinary Update Error: " . $e->getMessage());
                return response()->json(['message' => 'Image update failed.'], 500);
            }
        }

        $service->update($validated);

        return response()->json($service);
    }

    // Admin Only: Delete Service
    public function destroy(Request $request, $id)
    {
        $service = Service::findOrFail($id);
        $service->delete(); // Soft delete

        return response()->json(['message' => 'Service deleted successfully']);
    }
}
