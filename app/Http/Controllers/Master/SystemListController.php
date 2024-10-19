<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\SystemList;
use App\Models\TopicList;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class SystemListController extends Controller
{
    public function index(Request $request)
    {
        try {
            $perPage = $request->input('per_page', 10); // Set a default per page value
            $sortBy = $request->input('sort_by', 'topic'); // Sort by name by default
            $sortOrder = $request->input('sort_order', 'asc'); // Sort in ascending order by default
            $search = $request->input('search', ''); // Get the search term

            $datas = SystemList::where('id', 'like', "%{$search}%")
                ->orWhere('topic', 'like', "%{$search}%")
                ->orWhere('created_at', 'like', "%{$search}%")
                ->orderBy($sortBy, $sortOrder)
                ->paginate($perPage);

            // Add 'is_used' attribute to each question
            $datas->getCollection()->transform(function ($data) {
                $data->created_date = Carbon::parse($data->created_at)->format('d F Y H:i:s');
                return $data;
            });

            return response()->json(['error' => false, 'message' => 'Success fetch data system list', 'data' => $datas], 200);
        } catch (\Exception $e) {
            Log::error('Error fetch data system list: ' . $e->getMessage());
            return response()->json(['error' => true, 'message' => 'Error fetch data system list', 'error_message' => $e->getMessage()], 500);
        }
    }

    public function detail(Request $request)
    {
        try {
            $id = $request->input('id');
            if (empty($id)){
                return response()->json(['error' => true, 'message' => 'id is required', 'error_message' => 'id is required'], 422);
            }

            $data = SystemList::find($id);

            return response()->json(['error' => false, 'message' => 'Success get data', 'data' => $data], 200);
        } catch (\Exception $e) {
            Log::error('Error get data system: ' . $e->getMessage());
            return response()->json(['error' => true, 'message' => 'Error get data', 'error_message' => $e->getMessage()], 500);
        }
    }

    public function fetch(Request $request)
    {
        try {
            $data = SystemList::get();

            return response()->json(['error' => false, 'message' => 'Success fetch data', 'data' => $data], 200);
        } catch (\Exception $e) {
            Log::error('Error fetch data system list: ' . $e->getMessage());
            return response()->json(['error' => true, 'message' => 'Error fetch data', 'error_message' => $e->getMessage()], 500);
        }
    }

    public function upsert(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'topic' => 'required|string|max:255',
            'is_active' => 'required|integer|max:1'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => true, 'message' => $validator->errors()], 422);
        }

        try {
            $system = SystemList::where('topic', 'LIKE', '%'.$request->topic.'%')->first();
            if ($system) {
                $is_used = TopicList::where('system_id', $system->id)->first();
                if ($is_used){
                    return response()->json(['error' => true, 'message' => 'The system topic has been used'], 400);
                }
            }

            DB::beginTransaction();
            $result = SystemList::updateOrCreate(
                ['topic' => $request->topic],
                [
                    'topic' => $request->topic,
                    'is_active' => $request->is_active
                ]
            );

            if (!$result) {
                return response()->json(['error' => true, 'message' => "Upsert data failed"], 500);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            // Log or handle the exception
            Log::error('Error system upsert: ' . $e->getMessage());
            return response()->json(['error' => true, 'message' => 'Error Upsert data', 'error_message' => $e->getMessage()], 500);
        }
        return response()->json(['error' => false, 'message' => 'Upsert data successfully'], 201);
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
            $system = SystemList::where('topic', 'LIKE', '%'.$request->topic.'%')->first();
            $is_used = TopicList::where('system_id', $system->id)->first();
            if ($is_used){
                return response()->json(['error' => true, 'message' => 'The system topic has been used'], 400);
            }

            DB::beginTransaction();
            $data = SystemList::where('id', $request->id);
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
            Log::error('Error deleting topic: ' . $e->getMessage());
            return response()->json(['error' => true, 'message' => 'Error deleting the data', 'error_message' => $e->getMessage()], 500);
        }
    }
}
