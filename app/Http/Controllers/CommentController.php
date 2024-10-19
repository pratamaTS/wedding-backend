<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class CommentController extends Controller
{
    public function index(Request $request)
    {
        try {
            $perPage = $request->input('per_page', 10); // Set a default per page value
            $sortBy = $request->input('sort_by', 'name'); // Sort by name by default
            $sortOrder = $request->input('sort_order', 'desc'); // Sort in ascending order by default
            $search = $request->input('search', '');

            $datas = Comment::where('id', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%")->orderBy($sortBy, $sortOrder)->paginate($perPage);

            $datas->getCollection()->transform(function ($data) {
                $data->created_date = Carbon::parse($data->created_at)->format('d F Y H:i:s');
                return $data;
            });

            return response()->json(['error' => false, 'message' => 'Success fetch data comments', 'data' => $datas], 200);
        } catch (\Exception $e) {
            Log::error('Error fetch data comments: ' . $e->getMessage());
            return response()->json(['error' => true, 'message' => 'Error fetch data comments', 'error_message' => $e->getMessage()], 500);
        }
    }

    public function upsert(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:150',
            'status' => 'required|string|in:Hadir,Tidak Hadir',
            'guest_count' => 'required|integer|in:0,1,2',
            'comment' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => true, 'message' => $validator->errors()], 422);
        }

        try {
            DB::beginTransaction();
            $comment = Comment::updateOrCreate(
                ['name' => $request->name],
                [
                    'name' => $request->name,
                    'status' => $request->status,
                    'guest_count' => $request->guest_count,
                    'comment' => $request->comment
                ]
            );

            if (!$comment) {
                return response()->json(['error' => true, 'message' => "Upsert comment data failed"], 500);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            // Log or handle the exception
            Log::error('Error save comment: ' . $e->getMessage());
            return response()->json(['error' => true, 'message' => 'Error Upsert comment data', 'description' => $e->getMessage()], 500);
        }
        return response()->json(['error' => false, 'message' => 'Upsert comment data successfully'], 201);
    }
}
