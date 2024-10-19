<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\LabValue;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class LabValuesController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 10); // Set a default per page value
        $sortBy = $request->input('sort_by', 'indicator'); // Sort by name by default
        $sortOrder = $request->input('sort_order', 'asc'); // Sort in ascending order by default
        $search = $request->input('search', '');

        // Retrieve lab values along with their associated categories
        $datas = LabValue::with(['categoryLab:id,name'])
                    ->when($search, function ($query) use ($search) {
                        $query->whereHas('categoryLab', function ($q) use ($search) {
                            $q->where('name', 'like', "%{$search}%");
                        })
                        ->orWhere('id', 'like', "%{$search}%")
                        ->orWhere('indicator', 'like', "%{$search}%")
                        ->orWhere('unit', 'like', "%{$search}%")
                        ->orWhere('reference_value', 'like', "%{$search}%")
                        ->orWhere('created_at', 'like', "%{$search}%"); // Add more columns as needed
                    })
                    ->orderBy($sortBy, $sortOrder)
                    ->paginate($perPage);

        $datas->getCollection()->transform(function ($data) {
            $data->created_date = Carbon::parse($data->created_at)->format('d F Y H:i:s');
            return $data;
        });

        // Return the lab values to the view or process them as needed
        return response()->json(['error' => false, 'message' => 'Success fetch data lab values', 'data' => $datas], 200);
    }

    public function indexStudent()
    {
        // Retrieve lab values along with their associated categories
        $labValues = LabValue::with('categoryLab')->get();

        // Return the lab values to the view or process them as needed
        return response()->json(['error' => false, 'message' => 'Success fetch data lab values', 'data' => $labValues], 200);
    }

    public function detail(Request $request)
    {
        try {
            $id = $request->input('id');
            if (empty($id)){
                return response()->json(['error' => true, 'message' => 'id is required', 'error_message' => 'id is required'], 422);
            }

            $labValues = LabValue::find($id);

            return response()->json(['error' => false, 'message' => 'Success get data lab values', 'data' => $labValues], 200);
        } catch (\Exception $e) {
            Log::error('Error fetch data lab values: ' . $e->getMessage());
            return response()->json(['error' => true, 'message' => 'Error get data lab values', 'error_message' => $e->getMessage()], 500);
        }
    }

    public function upsert(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'category_lab_id' => 'required|integer',
            'indicator' => 'required|string|max:255',
            'unit' => 'required|string|max:255',
            'reference_value' => 'required|string|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => true, 'message' => $validator->errors()], 422);
        }

        try {
            DB::beginTransaction();
            $labValues = LabValue::updateOrCreate(
                ['indicator' => $request->old_indicator],
                [
                    'category_lab_id' => $request->category_lab_id,
                    'indicator' => $request->indicator,
                    'unit'=> $request->descriunitption,
                    'reference_value' => $request->reference_value
                ]
            );

            if (!$labValues) {
                return response()->json(['error' => true, 'message' => "Upsert lab value data failed"], 500);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            // Log or handle the exception
            Log::error('Error lab value: ' . $e->getMessage());
            return response()->json(['error' => true, 'message' => 'Error Upsert lab value data', 'error_message' => $e->getMessage()], 500);
        }
        return response()->json(['error' => false, 'message' => 'Upsert lab value data successfully'], 201);
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
            DB::beginTransaction();
            $data = LabValue::where('id', $request->id);
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
            Log::error('Error deleting lab values: ' . $e->getMessage());
            return response()->json(['error' => true, 'message' => 'Error deleting the data', 'error_message' => $e->getMessage()], 500);
        }
    }
}
