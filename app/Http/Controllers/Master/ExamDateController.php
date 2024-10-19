<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Models\ExamDate;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ExamDateController extends Controller
{
    public function index(Request $request)
    {
        try {
            $perPage = $request->input('per_page', 10); // Set a default per page value
            $sortBy = $request->input('sort_by', 'date'); // Sort by name by default
            $sortOrder = $request->input('sort_order', 'asc'); // Sort in ascending order by default
            $search = $request->input('search', '');

            $datas = ExamDate::where(DB::raw('DATE_FORMAT(date, "%Y-%m-%d")'), 'like', "%{$search}%")
                        ->orWhere('id', 'like', "%{$search}%")
                        ->orderBy($sortBy, $sortOrder)
                        ->paginate($perPage);

            $datas->getCollection()->transform(function ($data) {
                $data->date = Carbon::parse($data->date)->format('d F Y H:i:s');
                $data->created_date = Carbon::parse($data->created_at)->format('d F Y H:i:s');
                return $data;
            });

            return response()->json(['error' => false, 'message' => 'Success fetch data exam date', 'data' => $datas], 200);
        } catch (\Exception $e) {
            Log::error('Error fetch data exam date: ' . $e->getMessage());
            return response()->json(['error' => true, 'message' => 'Error fetch data exam date', 'error_message' => $e->getMessage()], 500);
        }
    }

    public function indexPublic(Request $request)
    {
        try {
            $sortBy = $request->input('sort_by', 'date'); // Sort by name by default
            $sortOrder = $request->input('sort_order', 'asc'); // Sort in
            $exam_dates = ExamDate::where('is_active', true)->orderBy($sortBy, $sortOrder)->get();
            foreach($exam_dates as $item){
                $item->name = Carbon::parse($item->date)->setTimezone('UTC')->toDateString();
            }

            return response()->json(['error' => false, 'message' => 'Success fetch data exam date', 'data' => $exam_dates], 200);
        } catch (\Exception $e) {
            Log::error('Error fetch data Educational Status: ' . $e->getMessage());
            return response()->json(['error' => true, 'message' => 'Error fetch data exam date', 'error_message' => $e->getMessage()], 500);
        }
    }

    public function upsert(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'date' => 'required|date',
            'is_active' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => true, 'errors' => $validator->errors()], 422);
        }

        try {
            // Use updateOrCreate to either update an existing record or create a new one
            $exam_date = ExamDate::updateOrCreate(
                ['date' => $request->date], // Condition to find the existing record
                ['date' => $request->date, 'is_active' => $request->is_active] // Values to update or create the record with
            );

            return response()->json(['error' => false, 'message' => 'Success upsert data exam date', 'data' => $exam_date], 200);
        } catch (\Exception $e) {
            Log::error('Error fetch data exam date: ' . $e->getMessage());
            return response()->json(['error' => true, 'message' => 'Error upsert data exam date', 'error_message' => $e->getMessage()], 500);
        }
    }

    public function detail(Request $request)
    {
        try {
            $id = $request->input('id');
            if (empty($id)){
                return response()->json(['error' => true, 'message' => 'id is required', 'error_message' => 'id is required'], 422);
            }

            $examDate = ExamDate::find($id);

            return response()->json(['error' => false, 'message' => 'Success get data exam date', 'data' => $examDate], 200);
        } catch (\Exception $e) {
            Log::error('Error fetch data exam date: ' . $e->getMessage());
            return response()->json(['error' => true, 'message' => 'Error get data exam date', 'error_message' => $e->getMessage()], 500);
        }
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
            $is_used = User::where('exam_date_id', $request->id)->first();
            if ($is_used){
                return response()->json(['error' => true, 'message' => 'The exam date has been used'], 400);
            }

            DB::beginTransaction();
            $data = ExamDate::where('id', $request->id);
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
