<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class UniversityController extends Controller
{
    public function index(Request $request)
    {
        try {
            $perPage = $request->input('per_page', 10); // Set a default per page value
            $sortBy = $request->input('sort_by', 'name'); // Sort by name by default
            $sortOrder = $request->input('sort_order', 'desc'); // Sort in ascending order by default
            $search = $request->input('search', '');

            $datas = University::where('id', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%")->orderBy($sortBy, $sortOrder)->paginate($perPage);

            $datas->getCollection()->transform(function ($data) {
                $data->created_date = Carbon::parse($data->created_at)->format('d F Y H:i:s');
                return $data;
            });

            return response()->json(['error' => false, 'message' => 'Success fetch data university', 'university' => $datas], 200);
        } catch (\Exception $e) {
            Log::error('Error fetch data university: ' . $e->getMessage());
            return response()->json(['error' => true, 'message' => 'Error fetch data university', 'error_message' => $e->getMessage()], 500);
        }
    }

    public function indexAdmin(Request $request)
    {
        try {
            $perPage = $request->input('per_page', 10); // Set a default per page value
            $sortBy = $request->input('sort_by', 'name'); // Sort by name by default
            $sortOrder = $request->input('sort_order', 'desc'); // Sort in ascending order by default
            $search = $request->input('search', '');

            $datas = University::where('name', 'like', "%{$search}%")->orderBy($sortBy, $sortOrder)->paginate($perPage);

            $datas->getCollection()->transform(function ($data) {
                $data->created_date = Carbon::parse($data->created_at)->format('d F Y H:i:s');
                return $data;
            });

            return response()->json(['error' => false, 'message' => 'Success fetch data university', 'data' => $datas], 200);
        } catch (\Exception $e) {
            Log::error('Error fetch data university: ' . $e->getMessage());
            return response()->json(['error' => true, 'message' => 'Error fetch data university', 'error_message' => $e->getMessage()], 500);
        }
    }

    public function indexPublic(Request $request)
    {
        try {
            $university = University::get();

            return response()->json(['error' => false, 'message' => 'Success fetch data university', 'data' => $university], 200);
        } catch (\Exception $e) {
            Log::error('Error fetch data university: ' . $e->getMessage());
            return response()->json(['error' => true, 'message' => 'Error fetch data university', 'error_message' => $e->getMessage()], 500);
        }
    }

    public function detail(Request $request)
    {
        try {
            $id = $request->input('id');
            if (empty($id)){
                return response()->json(['error' => true, 'message' => 'id is required', 'error_message' => 'id is required'], 422);
            }

            $university = University::find($id);

            return response()->json(['error' => false, 'message' => 'Success get data university', 'university' => $university], 200);
        } catch (\Exception $e) {
            Log::error('Error fetch data Educational Status: ' . $e->getMessage());
            return response()->json(['error' => true, 'message' => 'Error get data university', 'error_message' => $e->getMessage()], 500);
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
            $university = University::updateOrCreate(
                ['name' => $request->name],
                [
                    'name' => $request->name,
                    'is_active' => $request->is_active
                ]
            );

            if (!$university) {
                return response()->json(['error' => true, 'message' => "Upsert university data failed"], 500);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            // Log or handle the exception
            Log::error('Error university: ' . $e->getMessage());
            return response()->json(['error' => true, 'message' => 'Error Upsert university data', 'error_message' => $e->getMessage()], 500);
        }
        return response()->json(['error' => false, 'message' => 'Upsert university data successfully'], 201);
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
            $is_used = User::where('university_id', $request->id)->first();
            if ($is_used){
                return response()->json(['error' => true, 'message' => 'The university has been used'], 400);
            }

            DB::beginTransaction();
            $data = University::where('id', $request->id);
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
            Log::error('Error deleting university: ' . $e->getMessage());
            return response()->json(['error' => true, 'message' => 'Error deleting the data', 'error_message' => $e->getMessage()], 500);
        }
    }
}
