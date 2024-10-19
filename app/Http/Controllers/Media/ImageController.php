<?php

namespace App\Http\Controllers\Media;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ImageController extends Controller
{
    public function index(Request $request)
    {
        try {
            $perPage = $request->input('per_page', 10); // Set a default per page value
            $sortBy = $request->input('sort_by', 'created_at'); // Sort by created_at by default
            $sortOrder = $request->input('sort_order', 'desc'); // Sort in descending order by default
            $search = $request->input('search'); // Get the search query parameter

            // Get the list of images from the storage directory
            $images = collect(Storage::disk('public')->files('images'))
                ->filter(function ($file) {
                    return in_array(pathinfo($file, PATHINFO_EXTENSION), ['jpeg', 'png', 'jpg', 'gif']);
                })
                ->map(function ($file) {
                    return [
                        'filename' => basename($file),
                        'url' => url(Storage::url($file)),
                        'created_at' => Storage::disk('public')->lastModified($file),
                    ];
                });

            // Filter the images based on the search query
            if ($search) {
                $images = $images->filter(function ($image) use ($search) {
                    return strpos($image['filename'], $search) !== false || strpos($image['url'], $search) !== false;
                });
            }

            // Sort and paginate the images
            $images = $images->sortBy($sortBy, SORT_REGULAR, $sortOrder === 'desc')->values()->all();
            $page = LengthAwarePaginator::resolveCurrentPage();
            $pagination = new LengthAwarePaginator(
                array_slice($images, ($page - 1) * $perPage, $perPage),
                count($images),
                $perPage,
                $page,
                ['path' => LengthAwarePaginator::resolveCurrentPath()]
            );

            return response()->json(['error' => false, 'message' => 'Success fetch data images', 'data' => $pagination], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching images: ' . $e->getMessage());
            return response()->json(['error' => true, 'message' => 'Error fetching images', 'error_message' => $e->getMessage()], 500);
        }
    }

    public function uploadOrSyncImage(Request $request)
    {
        try {
            // Validate the incoming request
            $request->validate([
                'image' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
            ]);

            // Get the uploaded file
            $file = $request->file('image');

            // Generate a unique filename based on the original filename
            $originalFilename = $file->getClientOriginalName();
            $filename = pathinfo($originalFilename, PATHINFO_FILENAME);
            $extension = $file->getClientOriginalExtension();
            $filenameToStore = $filename . '.' . $extension;

            // Check if file already exists
            if (Storage::disk('public')->exists('images/' . $filenameToStore)) {
                // Delete the existing file
                Storage::disk('public')->delete('images/' . $filenameToStore);
            }

            // Save the file to the 'public/images' directory
            $path = $file->storeAs('public/images', $filenameToStore);

            // Generate the URL
            $imageUrl = Storage::url($path);

            return response()->json([
                'error' => false,
                'message' => 'Image uploaded successfully',
                'data' => ['image_url' => url($imageUrl)],
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error uploading image: ' . $e->getMessage());
            return response()->json([
                'error' => true,
                'message' => 'Error uploading image',
                'error_message' => $e->getMessage(),
            ], 500);
        }
    }
}
