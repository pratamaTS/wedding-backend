<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\CategoryLab;
use App\Models\LabValue;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class CategoryLabController extends Controller
{
    public function index(Request $request)
    {
        try {
            $perPage = $request->input('per_page', 10); // Set a default per page value
            $sortBy = $request->input('sort_by', 'name'); // Sort by name by default
            $sortOrder = $request->input('sort_order', 'asc'); // Sort in ascending order by default
            $search = $request->input('search', '');

            $datas = CategoryLab::where('id', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%")->orderBy($sortBy, $sortOrder)->paginate($perPage);

            $datas->getCollection()->transform(function ($data) {
                $data->created_date = Carbon::parse($data->created_at)->format('d F Y H:i:s');
                return $data;
            });

            return response()->json(['error' => false, 'message' => 'Success fetch data category lab', 'data' => $datas], 200);
        } catch (\Exception $e) {
            Log::error('Error fetch data category lab: ' . $e->getMessage());
            return response()->json(['error' => true, 'message' => 'Error fetch data category lab', 'error_message' => $e->getMessage()], 500);
        }
    }

    public function fetch(Request $request)
    {
        try {
            $search = $request->input('search', '');
            $data = CategoryLab::where('subtopic', 'LIKE', '%'.$search.'%')->get();

            return response()->json(['error' => false, 'message' => 'Success fetch data', 'data' => $data], 200);
        } catch (\Exception $e) {
            Log::error('Error fetch data category lab: ' . $e->getMessage());
            return response()->json(['error' => true, 'message' => 'Error fetch data', 'error_message' => $e->getMessage()], 500);
        }
    }

    public function detail(Request $request)
    {
        try {
            $id = $request->input('id');
            if (empty($id)){
                return response()->json(['error' => true, 'message' => 'id is required', 'error_message' => 'id is required'], 422);
            }

            $categoryLab = CategoryLab::find($id);

            return response()->json(['error' => false, 'message' => 'Success get data category lab', 'data' => $categoryLab], 200);
        } catch (\Exception $e) {
            Log::error('Error fetch data category lab: ' . $e->getMessage());
            return response()->json(['error' => true, 'message' => 'Error get data category lab', 'error_message' => $e->getMessage()], 500);
        }
    }

    public function upsert(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => true, 'message' => $validator->errors()], 422);
        }

        try {
            DB::beginTransaction();
            $categoryLab = CategoryLab::updateOrCreate(
                ['name' => $request->name],
                ['name' => $request->name]
            );

            if (!$categoryLab) {
                return response()->json(['error' => true, 'message' => "Upsert category lab data failed"], 500);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            // Log or handle the exception
            Log::error('Error category lab: ' . $e->getMessage());
            return response()->json(['error' => true, 'message' => 'Error Upsert category lab data', 'error_message' => $e->getMessage()], 500);
        }
        return response()->json(['error' => false, 'message' => 'Upsert category lab data successfully'], 201);
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
            $is_used = LabValue::where('category_lab_id', $request->id)->first();
            if ($is_used){
                return response()->json(['error' => true, 'message' => 'The categoiry lab has been used'], 400);
            }

            DB::beginTransaction();
            $data = CategoryLab::where('id', $request->id);
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
            Log::error('Error deleting category lab: ' . $e->getMessage());
            return response()->json(['error' => true, 'message' => 'Error deleting the data', 'error_message' => $e->getMessage()], 500);
        }
    }
}
