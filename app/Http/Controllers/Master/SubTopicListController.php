<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\Question;
use App\Models\SubTopicList;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class SubTopicListController extends Controller
{
    public function index(Request $request)
    {
        try {
            $perPage = $request->input('per_page', 10); // Set a default per page value
            $sortBy = $request->input('sort_by', 'subtopic'); // Sort by name by default
            $sortOrder = $request->input('sort_order', 'asc'); // Sort in ascending order by default
            $search = $request->input('search', ''); // Get the search term

            $subtopics = SubTopicList::with(['topic:id,topic'])
                ->when($search, function ($query) use ($search) {
                    $query->whereHas('topic', function ($q) use ($search) {
                        $q->where('topic', 'like', "%{$search}%");
                    })
                    ->orWhere('id', 'like', "%{$search}%")
                    ->orWhere('subtopic', 'like', "%{$search}%")
                        ->orWhere('created_at', 'like', "%{$search}%"); // Add more columns as needed
                })
                ->orderBy($sortBy, $sortOrder)
                ->paginate($perPage);

            // Add 'is_used' attribute to each question
            $subtopics->getCollection()->transform(function ($subtopic) {
                $subtopic->created_date = Carbon::parse($subtopic->created_at)->format('d F Y H:i:s');
                return $subtopic;
            });

            return response()->json(['error' => false, 'message' => 'Success fetch data sub topic', 'data' => $subtopics], 200);
        } catch (\Exception $e) {
            Log::error('Error fetch data sub topic: ' . $e->getMessage());
            return response()->json(['error' => true, 'message' => 'Error fetch data sub topic', 'error_message' => $e->getMessage()], 500);
        }
    }

    public function detail(Request $request)
    {
        try {
            $id = $request->input('id');
            if (empty($id)){
                return response()->json(['error' => true, 'message' => 'id is required', 'error_message' => 'id is required'], 422);
            }

            $data = SubTopicList::find($id);

            return response()->json(['error' => false, 'message' => 'Success get data', 'data' => $data], 200);
        } catch (\Exception $e) {
            Log::error('Error get data subtopic: ' . $e->getMessage());
            return response()->json(['error' => true, 'message' => 'Error get data', 'error_message' => $e->getMessage()], 500);
        }
    }

    public function fetch(Request $request)
    {
        try {
            $search = $request->input('search', '');
            $subTopicList = SubTopicList::where('subtopic', 'LIKE', '%'.$search.'%')->get();

            return response()->json(['error' => false, 'message' => 'Success fetch data sub topic list', 'data' => $subTopicList], 200);
        } catch (\Exception $e) {
            Log::error('Error fetch data sub topic list: ' . $e->getMessage());
            return response()->json(['error' => true, 'message' => 'Error fetch data sub topic list', 'error_message' => $e->getMessage()], 500);
        }
    }

    public function upsert(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'topic_id' => 'required|integer',
            'subtopic' => 'required|string|max:255',
            'competence' => [
                'required',
                'numeric',
                'regex:/^\d+(\.\d{1,2})?$/', // Ensures a floating-point number with up to 2 decimal places
                'min:0',
                'max:4'
            ],
            'is_active' => 'required|integer|max:1'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => true, 'message' => $validator->errors()], 422);
        }

        try {
            $subtopic = SubTopicList::where('subtopic', 'LIKE', '%'.$request->subtopic.'%')->first();
            if ($subtopic){
                $is_used = Question::where('subtopic_list_id', $subtopic->id)->first();
                if ($is_used){
                    return response()->json(['error' => true, 'message' => 'The subtopic has been used'], 400);
                }
            }

            DB::beginTransaction();
            $result = SubTopicList::updateOrCreate(
                ['subtopic' => $request->subtopic],
                [
                    'topic_id' => $request->topic_id,
                    'subtopic' => $request->subtopic,
                    'competence' => $request->competence,
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
            Log::error('Error subtopic upsert: ' . $e->getMessage());
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
            $subtopic = SubTopicList::where('subtopic', 'LIKE', '%'.$request->subtopic.'%')->first();
            if ($subtopic){
                $is_used = Question::where('subtopic_list_id', $subtopic->id)->first();
                if ($is_used){
                    return response()->json(['error' => true, 'message' => 'The subtopic has been used'], 400);
                }
            }

            DB::beginTransaction();
            $data = SubTopicList::where('id', $request->id);
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
            Log::error('Error deleting subtopic: ' . $e->getMessage());
            return response()->json(['error' => true, 'message' => 'Error deleting the data', 'error_message' => $e->getMessage()], 500);
        }
    }
}
