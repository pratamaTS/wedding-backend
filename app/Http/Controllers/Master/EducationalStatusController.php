<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\EducationalStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class EducationalStatusController extends Controller
{
    public function index(Request $request)
    {
        try {
            $perPage = $request->input('per_page', 10); // Set a default per page value
            $sortBy = $request->input('sort_by', 'name'); // Sort by name by default
            $sortOrder = $request->input('sort_order', 'asc'); // Sort in ascending order by default
            $search = $request->input('search', '');

            $educational_status = EducationalStatus::where('id', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%")->orderBy($sortBy, $sortOrder)->paginate($perPage);

            return response()->json(['error' => false, 'message' => 'Success fetch data educational status', 'data' => $educational_status], 200);
        } catch (\Exception $e) {
            Log::error('Error fetch data Educational Status: ' . $e->getMessage());
            return response()->json(['error' => true, 'message' => 'Error fetch data educational status', 'error_message' => $e->getMessage()], 500);
        }
    }

    public function indexPublic(Request $request)
    {
        try {
            $educational_status = EducationalStatus::get();

            return response()->json(['error' => false, 'message' => 'Success fetch data educational status', 'data' => $educational_status], 200);
        } catch (\Exception $e) {
            Log::error('Error fetch data Educational Status: ' . $e->getMessage());
            return response()->json(['error' => true, 'message' => 'Error fetch data educational status', 'error_message' => $e->getMessage()], 500);
        }
    }

    public function detail(Request $request)
    {
        try {
            $id = $request->input('id');
            if (empty($id)){
                return response()->json(['error' => true, 'message' => 'id is required', 'error_message' => 'id is required'], 422);
            }

            $educational_status = EducationalStatus::find($id);

            return response()->json(['error' => false, 'message' => 'Success get data educational status', 'educational_status' => $educational_status], 200);
        } catch (\Exception $e) {
            Log::error('Error fetch data Educational Status: ' . $e->getMessage());
            return response()->json(['error' => true, 'message' => 'Error get data educational status', 'error_message' => $e->getMessage()], 500);
        }
    }

    public function upsert(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'is_active' => 'required|integer|max:1'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => true, 'message' => $validator->errors()], 422);
        }

        try {
            DB::beginTransaction();
            $educational_status = EducationalStatus::updateOrCreate(
                ['name' => $request->name],
                ['name' => $request->name, 'is_active' => $request->is_active]
            );

            if (!$educational_status) {
                return response()->json(['error' => true, 'message' => "Upsert educational status data failed"], 500);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            // Log or handle the exception
            Log::error('Error educational status: ' . $e->getMessage());
            return response()->json(['error' => true, 'message' => 'Error Upsert educational status data', 'error_message' => $e->getMessage()], 500);
        }
        return response()->json(['error' => false, 'message' => 'Upsert educational status data successfully'], 201);
    }

    public function delete(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => true, 'message' => $validator->errors()], 422);
        }

        try {
            $is_used = User::where('educational_status_id', $request->id)->first();
            if ($is_used){
                return response()->json(['error' => true, 'message' => 'The educational status has been used'], 400);
            }

            DB::beginTransaction();
            $data = EducationalStatus::where('id', $request->id);
            if (!$data) {
                return response()->json(['error' => true, 'message' => 'data not found'], 404);
            }

            // Perform the delete operation
            $data->delete();
            DB::commit();

            return response()->json(['error' => false, 'message' => 'The data deleted successfully'], 200);
        } catch (\Exception $e) {
            DB::rollback();
            // Log or handle the exception
            Log::error('Error deleting educational status: ' . $e->getMessage());
            return response()->json(['error' => true, 'message' => 'Error deleting the data', 'error_message' => $e->getMessage()], 500);
        }
    }
}
