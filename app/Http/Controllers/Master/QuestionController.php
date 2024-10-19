<?php

namespace App\Http\Controllers\Master;

use App\Exports\QuestionsTemplateExport;
use App\Http\Controllers\Controller;
use App\Imports\QuestionsImport;
use App\Models\Question;
use App\Models\StudentToDoList;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;

class QuestionController extends Controller
{
    public function index(Request $request)
    {
        try {
            $perPage = $request->input('per_page', 10); // Set a default per page value
            $sortBy = $request->input('sort_by', 'question_packet_id'); // Sort by name by default
            $sortOrder = $request->input('sort_order', 'asc'); // Sort in ascending order by default
            $search = $request->input('search'); // Sort in ascending order by default
            $question_packet_id = $request->input('question_packet_id');

            $questions = Question::with(['questionPacket:id,name', 'subtopicList:id,subtopic'])
                        ->when($question_packet_id, function ($query) use ($question_packet_id) {
                            $query->where('question_packet_id', $question_packet_id);
                        })
                        ->when($search, function ($query) use ($search) {
                            $query->where(function ($query) use ($search) {
                                $query->whereHas('questionPacket', function ($q) use ($search) {
                                        $q->where('name', 'like', "%{$search}%");
                                    })
                                    ->orWhereHas('subtopicList', function ($q) use ($search) {
                                        $q->where('subtopic', 'like', "%{$search}%");
                                    })
                                    ->orWhere('id', 'like', "%{$search}%")
                                    ->orWhere('question_number', 'like', "%{$search}%")
                                    ->orWhere('question', 'like', "%{$search}%")
                                    ->orWhere('option_a', 'like', "%{$search}%")
                                    ->orWhere('option_b', 'like', "%{$search}%")
                                    ->orWhere('option_c', 'like', "%{$search}%")
                                    ->orWhere('option_d', 'like', "%{$search}%")
                                    ->orWhere('option_e', 'like', "%{$search}%")
                                    ->orWhere('correct_answer', 'like', "%{$search}%")
                                    ->orWhere('image_url', 'like', "%{$search}%")
                                    ->orWhere('discussion', 'like', "%{$search}%")
                                    ->orWhere('created_at', 'like', "%{$search}%");
                            });
                        })
                        ->orderBy($sortBy, $sortOrder)
                        ->orderBy('question_number', 'asc')
                        ->paginate($perPage);

            // Add 'is_used' attribute to each question
            $questions->getCollection()->transform(function ($question) {
                // $question->is_used = StudentToDoList::where('question_packet_id', $question->question_packet_id)->exists();
                $question->is_used = false;
                $question->created_date = Carbon::parse($question->created_at)->format('d F Y H:i:s');
                return $question;
            });

            return response()->json(['error' => false, 'message' => 'Success fetch data question', 'data' => $questions], 200);
        } catch (\Exception $e) {
            Log::error('Error fetch data question: ' . $e->getMessage());
            return response()->json(['error' => true, 'message' => 'Error fetch data question', 'error_message' => $e->getMessage()], 500);
        }
    }

    public function detail(Request $request)
    {
        try {
            $id = $request->input('id');
            if (empty($id)){
                return response()->json(['error' => true, 'message' => 'id is required', 'error_message' => 'id is required'], 422);
            }

            $question = Question::find($id);

            return response()->json(['error' => false, 'message' => 'Success get data category lab', 'data' => $question], 200);
        } catch (\Exception $e) {
            Log::error('Error fetch data exam date: ' . $e->getMessage());
            return response()->json(['error' => true, 'message' => 'Error get data category lab', 'error_message' => $e->getMessage()], 500);
        }
    }

    public function upsert(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'question_packet_id' => 'required|integer',
            'subtopic_list_id' => 'required|integer',
            'question_number' => 'required|integer',
            'scenario' => 'required|string',
            'question' => 'required|string',
            'option_a' => 'required|string',
            'option_b' => 'required|string',
            'option_c' => 'required|string',
            'option_d' => 'required|string',
            'option_e' => 'required|string',
            'correct_answer' => 'required|string',
            'image_url' => 'nullable|string',
            'discussion' => 'required|string',
            'is_active' => 'required|integer|max:1'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => true, 'message' => $validator->errors()], 422);
        }

        try {
            // $hasStarted = StudentToDoList::where('question_packet_id', $request->question_packet_id)
            // ->whereNotNull('start_date')
            // ->first();

            // if ($hasStarted) {
            //     return response()->json(['error' => true, 'message' => 'The question packet has already started for a student'], 400);
            // }

            DB::beginTransaction();

            $question = Question::updateOrCreate(
                [
                    'question_packet_id' => $request->question_packet_id,
                    'subtopic_list_id' => $request->subtopic_list_id,
                    'question_number' => $request->question_number
                ],
                [
                    'scenario' => $request->scenario,
                    'question' => $request->question,
                    'option_a' => $request->option_a,
                    'option_b' => $request->option_b,
                    'option_c' => $request->option_c,
                    'option_d' => $request->option_d,
                    'option_e' => $request->option_e,
                    'correct_answer' => $request->correct_answer,
                    'image_url' => $request->image_url ?? '',
                    'discussion' => $request->discussion,
                    'is_active' => $request->is_active
                ]
            );

            if (!$question) {
                return response()->json(['error' => true, 'message' => "Upsert question data failed"], 500);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            // Log or handle the exception
            Log::error('Error question: ' . $e->getMessage());
            return response()->json(['error' => true, 'message' => 'Error Upsert question data', 'error_message' => $e->getMessage()], 500);
        }

        return response()->json(['error' => false, 'message' => 'Upsert question data successfully'], 201);
    }

    public function delete(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'question_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => true, 'message' => $validator->errors()], 422);
        }

        try {
            $hasStarted = StudentToDoList::where('question_packet_id', $request->question_packet_id)
            ->whereNotNull('start_date')
            ->first();

            if ($hasStarted) {
                return response()->json(['error' => true, 'message' => 'The question packet has already started for a student'], 400);
            }

            DB::beginTransaction();
            $question = Question::where('id', $request->question_id);
            if (!$question) {
                return response()->json(['error' => true, 'message' => 'Question not found'], 404);
            }

            // Perform the delete operation
            $question->delete();
            DB::commit();

            return response()->json(['error' => false, 'message' => 'Question deleted successfully'], 200);
        } catch (\Exception $e) {
            DB::rollback();
            // Log or handle the exception
            Log::error('Error deleting question: ' . $e->getMessage());
            return response()->json(['error' => true, 'message' => 'Error deleting question', 'error_message' => $e->getMessage()], 500);
        }
    }

    public function bulkUpsert(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|mimes:csv,xlsx,xls',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => true, 'message' => $validator->errors()], 422);
        }

        try {
            // $hasStarted = StudentToDoList::where('question_packet_id', $request->question_packet_id)
            // ->whereNotNull('start_date')
            // ->first();

            // if ($hasStarted) {
            //     return response()->json(['error' => true, 'message' => 'The question packet has already started for a student'], 400);
            // }

            DB::beginTransaction();

            $file = $request->file('file');
            $import = new QuestionsImport();
            Excel::import($import, $file);

            if ($import->failures()->isNotEmpty()) {
                DB::rollback();
                return response()->json(['error' => true, 'message' => 'Error processing file', 'failures' => $import->failures()], 500);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            // Log or handle the exception
            Log::error('Error bulk upsert: ' . $e->getMessage());
            return response()->json(['error' => true, 'message' => 'Error bulk upsert', 'error_message' => $e->getMessage()], 500);
        }

        return response()->json(['error' => false, 'message' => 'Bulk upsert completed successfully'], 201);
    }

    public function downloadTemplate()
    {
        return Excel::download(new QuestionsTemplateExport, 'questions_template.xlsx');
    }

    public function getNextQuestionNumber(Request $request)
    {
        try {
            $questionPacketId = $request->input('question_packet_id');
            $nextNumber = 1;
            if ($questionPacketId) {
                $question = Question::where('question_packet_id', $questionPacketId)
                            ->orderBy('question_number', 'desc')
                            ->first();

                $nextNumber = $question ? $question->question_number + 1 : 1;
            }

            return response()->json(['error' => false, 'message' => 'Success get next number question', 'data' => ["next_number" => $nextNumber]], 200);
        } catch (\Exception $e) {
            Log::error('Error get next number question: ' . $e->getMessage());
            return response()->json(['error' => true, 'message' => 'Error get next number question', 'error_message' => $e->getMessage()], 500);
        }
    }
}
