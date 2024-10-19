<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Models\StudentAnswer;
use App\Models\QuestionPacket;
use App\Models\Question;
use App\Models\StudentToDoList;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class QuestionPacketController extends Controller
{
    public function index(Request $request)
    {
        try {
            $perPage = $request->input('per_page', 10); // Set a default per page value
            $sortBy = $request->input('sort_by', 'name'); // Sort by name by default
            $sortOrder = $request->input('sort_order', 'asc'); // Sort in ascending order by default
            $search = $request->input('search', ''); // Get the search term

            $question_packets = QuestionPacket::query()
                ->when($search, function ($query) use ($search) {
                    $query->where('id', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('created_at', 'like', "%{$search}%"); // Add more columns as needed
                })
                ->orderBy($sortBy, $sortOrder)
                ->paginate($perPage);

            // Add 'is_used' attribute to each question
            $question_packets->getCollection()->transform(function ($question_packet) {
                $question_packet->is_used = StudentToDoList::where('question_packet_id', $question_packet->id)->exists();
                $question_packet->created_date = Carbon::parse($question_packet->created_at)->format('d F Y H:i:s');
                return $question_packet;
            });

            return response()->json(['error' => false, 'message' => 'Success fetch data question packet', 'data' => $question_packets], 200);
        } catch (\Exception $e) {
            Log::error('Error fetch data question packet: ' . $e->getMessage());
            return response()->json(['error' => true, 'message' => 'Error fetch data question packet', 'error_message' => $e->getMessage()], 500);
        }
    }

    public function indexStudent(Request $request)
    {
        try {
            $user = Auth::guard('api')->user(); // Fetch the authenticated admin
            if (!$user) {
                return response()->json(['error' => true, 'message' => 'Unauthorized'], 401);
            }
            $userMembership = $user->userMembership;
            $membership = $userMembership->membership;

            $accessCount = 0;
            switch (strtolower($membership->name)) {
                case 'trial':
                    $accessCount = 1;
                    break;
                case 'silver':
                    $accessCount = 4;
                    break;
                case 'gold':
                    $accessCount = QuestionPacket::count();
                    break;
                default:
                    $accessCount = 0;
                    break;
            }

            $questionPackets = QuestionPacket::with('questions.subtopicList.topic')->orderByRaw("name='Paket Trial' DESC")->orderBy('id')->get();

            $data = $questionPackets->map(function ($questionPacket, $index) use ($accessCount, $user) {
                $isDone = !!$user->studentToDoLists()->where('question_packet_id', $questionPacket->id)->value('is_done');
                $userAnswered = $user->studentAnswers->where('question_packet_id', $questionPacket->id)->count();
                $totalQuestion = $questionPacket->questions->count();
                $isAccessed = $index < $accessCount ? true : false;
                $isCanBeDone = $userAnswered === $totalQuestion && $isDone && $totalQuestion != 0;
                $topics = $questionPacket->questions->pluck('subtopicList.topic')->unique()->pluck('topic')->toArray();
                $topicsCount = count($topics);

                // If the count of topics is greater than 6, limit it to the first 6 topics and append "Dan Lainnya"
                if ($topicsCount > 6) {
                    $topics = array_slice($topics, 0, 6);
                    $topics[] = "Dan Lainnya";
                }

                if ($questionPacket->id == 7) {
                    $isCanBeDone = true;
                }

                $formattedStartDate = null;
                $formattedFinishDate = null;

                $startDate = $user->studentToDoLists()
                            ->where('question_packet_id', $questionPacket->id)
                            ->value('start_date');

                $finishDate = $user->studentToDoLists()
                            ->where('question_packet_id', $questionPacket->id)
                            ->value('finish_date');

                if ($startDate){
                    // Parse the start date into a Carbon instance
                    $carbonStartDate = Carbon::parse($startDate);

                    // Format the start date as "27 Mei 2024"
                    $formattedStartDate = $carbonStartDate->translatedFormat('d F Y');
                }

                if ($finishDate){
                    // Parse the start date into a Carbon instance
                    $carbonFinishDate = Carbon::parse($finishDate);

                    // Format the start date as "27 Mei 2024"
                    $formattedFinishDate = $carbonFinishDate->translatedFormat('d F Y');
                }

                return [
                    'id' => $questionPacket->id,
                    'title' => $questionPacket->name,
                    'topics' => $topics,
                    'is_accessed' => $isAccessed,
                    'is_can_be_done' => $isCanBeDone,
                    'answer' => $user->studentAnswers->where('question_packet_id', $questionPacket->id)->count(),
                    'question' => $questionPacket->questions->count(),
                    'is_done' => !!$user->studentToDoLists()->where('question_packet_id', $questionPacket->id)->value('is_done'),
                    'start_date' => $formattedStartDate,
                    'finish_date' => $formattedFinishDate
                ];
            });

            return response()->json(['error' => false, 'message' => 'Success fetch data question packet', 'data' => $data], 200);
        } catch (\Exception $e) {
            Log::error('Error fetch data question packet: ' . $e->getMessage());
            return response()->json(['error' => true, 'message' => 'Error fetch data question packet', 'error_message' => $e->getMessage()], 500);
        }
    }

    public function dashboardOnProgressQuestion(Request $request)
    {
        try {
            $user = Auth::guard('api')->user(); // Fetch the authenticated admin
            if (!$user) {
                return response()->json(['error' => true, 'message' => 'Unauthorized'], 401);
            }
            $userMembership = $user->userMembership;
            $membership = $userMembership->membership;

            $accessCount = 0;
            switch (strtolower($membership->name)) {
                case 'trial':
                    $accessCount = 1;
                    break;
                case 'silver':
                    $accessCount = 4;
                    break;
                case 'gold':
                    $accessCount = QuestionPacket::count();
                    break;
                default:
                    $accessCount = 0;
                    break;
            }

            $questionPackets = QuestionPacket::with('questions.subtopicList.topic')->orderByRaw("name='Paket Trial' DESC")->orderBy('id')->get();

            $data = $questionPackets->map(function ($questionPacket, $index) use ($accessCount, $user) {
                $isDone = !!$user->studentToDoLists()->where('question_packet_id', $questionPacket->id)->value('is_done');
                $userAnswered = $user->studentAnswers->where('question_packet_id', $questionPacket->id)->count();
                $totalQuestion = $questionPacket->questions->count();
                $isAccessed = $index < $accessCount ? true : false;
                $isCanBeDone = $userAnswered === $totalQuestion && $isDone && $totalQuestion != 0;
                $topics = $questionPacket->questions->pluck('subtopicList.topic')->unique()->pluck('topic')->toArray();
                $topicsCount = count($topics);

                // If the count of topics is greater than 6, limit it to the first 6 topics and append "Dan Lainnya"
                if ($topicsCount > 6) {
                    $topics = array_slice($topics, 0, 6);
                    $topics[] = "Dan Lainnya";
                }

                if ($questionPacket->id == 7) {
                    $isCanBeDone = true;
                }

                $formattedStartDate = null;
                $formattedFinishDate = null;

                $startDate = $user->studentToDoLists()
                            ->where('question_packet_id', $questionPacket->id)
                            ->value('start_date');

                $finishDate = $user->studentToDoLists()
                            ->where('question_packet_id', $questionPacket->id)
                            ->value('finish_date');

                if ($startDate){
                    // Parse the start date into a Carbon instance
                    $carbonStartDate = Carbon::parse($startDate);

                    // Format the start date as "27 Mei 2024"
                    $formattedStartDate = $carbonStartDate->translatedFormat('d F Y');
                }

                if ($finishDate){
                    // Parse the start date into a Carbon instance
                    $carbonFinishDate = Carbon::parse($finishDate);

                    // Format the start date as "27 Mei 2024"
                    $formattedFinishDate = $carbonFinishDate->translatedFormat('d F Y');
                }

                return [
                    'id' => $questionPacket->id,
                    'title' => $questionPacket->name,
                    'topics' => $topics,
                    'is_accessed' => $isAccessed,
                    'is_can_be_done' => $isCanBeDone,
                    'answer' => $user->studentAnswers->where('question_packet_id', $questionPacket->id)->count(),
                    'question' => $questionPacket->questions->count(),
                    'is_done' => !!$user->studentToDoLists()->where('question_packet_id', $questionPacket->id)->value('is_done'),
                    'start_date' => $formattedStartDate,
                    'finish_date' => $formattedFinishDate
                ];
            });

             // Filter the data to include only those packets that are in progress
            $data = $data->filter(function ($packet) {
                return $packet['start_date'] !== null && !$packet['is_done'];
            })->values(); // Re-index the array after filtering


            return response()->json(['error' => false, 'message' => 'Success fetch data on progress question packet', 'data' => $data], 200);
        } catch (\Exception $e) {
            Log::error('Error fetch data question packet: ' . $e->getMessage());
            return response()->json(['error' => true, 'message' => 'Error fetch data on progress question packet', 'error_message' => $e->getMessage()], 500);
        }
    }

    public function dashboardIsDoneQuestion(Request $request)
    {
        try {
            $user = Auth::guard('api')->user(); // Fetch the authenticated admin
            if (!$user) {
                return response()->json(['error' => true, 'message' => 'Unauthorized'], 401);
            }
            $isDone = $user->studentToDoLists->where('is_done', 1)->count();

            $questionPacketTotal = QuestionPacket::with('questions.subtopicList.topic')->orderByRaw("name='Paket Trial' DESC")->orderBy('id')->count();

            $data = [
                "is_done" => $isDone,
                "question_packet_total" => $questionPacketTotal
            ];

            return response()->json(['error' => false, 'message' => 'Success fetch data dashboard is done question packet', 'data' => $data], 200);
        } catch (\Exception $e) {
            Log::error('Error fetch data dashboard is done question packet: ' . $e->getMessage());
            return response()->json(['error' => true, 'message' => 'Error fetch data dashboard is done question packet', 'error_message' => $e->getMessage()], 500);
        }
    }

    public function dashboardIsAvailableQuestion(Request $request)
    {
        try {
            $user = Auth::guard('api')->user(); // Fetch the authenticated admin
            if (!$user) {
                return response()->json(['error' => true, 'message' => 'Unauthorized'], 401);
            }
            $userMembership = $user->userMembership;
            $membership = $userMembership->membership;

            $accessCount = 0;
            switch (strtolower($membership->name)) {
                case 'trial':
                    $accessCount = 1;
                    break;
                case 'silver':
                    $accessCount = 4;
                    break;
                case 'gold':
                    $accessCount = QuestionPacket::count();
                    break;
                default:
                    $accessCount = 0;
                    break;
            }

            $isDone = $user->studentToDoLists->where('is_done', 1)->count();
            $questionPacketToGo = $accessCount - $isDone;

            $data = [
                "is_available" => $questionPacketToGo
            ];

            return response()->json(['error' => false, 'message' => 'Success fetch data is available question packet', 'data' => $data], 200);
        } catch (\Exception $e) {
            Log::error('Error fetch data question packet: ' . $e->getMessage());
            return response()->json(['error' => true, 'message' => 'Error fetch data is available question packet', 'error_message' => $e->getMessage()], 500);
        }
    }

    public function detail(Request $request)
    {
        try {
            $id = $request->input('id');
            if (empty($id)){
                return response()->json(['error' => true, 'message' => 'id is required', 'error_message' => 'id is required'], 422);
            }

            $user = Auth::guard('api')->user();
            if (!$user) {
                return response()->json(['error' => true, 'message' => 'Unauthorized'], 401);
            }

            $question_packet = QuestionPacket::with('questions.subtopicList.topic')->where('id', $id)->first();
            $totalQuestion = $question_packet->questions->count();

            // Check if the user has completed the current question packet
            $is_done = $user->studentToDoLists()->where('question_packet_id', $id)->value('is_done') ?: false;
            $currentAnswer = $user->studentAnswers()->where('question_packet_id', $id)->latest()->first();
            $nextQuestionNumber = 1;
            if ($currentAnswer && !$is_done) {
            // Fetch the next question number
                $nextQuestion = Question::where('id', '>', $currentAnswer->question_id)
                    ->where('question_packet_id', $id)
                    ->orderBy('question_number')
                    ->first();

                $nextQuestionNumber = $nextQuestion ? $nextQuestion->question_number : null;
            }

            unset($question_packet['questions']);
            $question_packet['total_question'] = $totalQuestion;
            $question_packet['next_question'] = $nextQuestionNumber;
            $question_packet['is_done'] = $is_done;

            return response()->json(['error' => false, 'message' => 'Success get data question packet', 'data' => $question_packet], 200);
        } catch (\Exception $e) {
            Log::error('Error fetch data Educational Status: ' . $e->getMessage());
            return response()->json(['error' => true, 'message' => 'Error get data question packet', 'error_message' => $e->getMessage()], 500);
        }
    }

    public function getQuestionNumbers(Request $request)
    {
        $questionPacketId = $request->input('id');
        if (empty($questionPacketId)){
            return response()->json(['error' => true, 'message' => 'id is required', 'error_message' => 'id is required'], 422);
        }

        try {
            // Find the question packet by its ID
            $questionPacket = QuestionPacket::findOrFail($questionPacketId);

            // Retrieve all the question numbers associated with the question packet
            $questionNumbers = $questionPacket->questions()->orderBy('question_number', 'asc')->select('id', 'question_number')->get();

            // Retrieve the current number the user is answering
            $currentNumber = 1;
            $answeredQuestions = null;
            $nextQuestion = null;

            // Determine which questions are filled by the user
            $filledQuestions = [];
            if (Auth::guard('api')->check()) {
                $user = Auth::guard('api')->user();
                $answeredQuestions = $user->studentAnswers()->where('question_packet_id', $questionPacketId)->get();
                $currentAnswer = $user->studentAnswers()->where('question_packet_id', $questionPacketId)->latest()->first();

                if($currentAnswer){
                    $nextQuestion = Question::where('id', '>', $currentAnswer->question_id)
                        ->where('question_packet_id', $questionPacketId)
                        ->orderBy('question_number')
                        ->first();
                }

                $nextNumber = $nextQuestion ? $nextQuestion->question_number : 1;
                $currentNumber = $nextNumber;

                foreach ($questionNumbers as $question) {
                    $isCurrent = ($question->question_number == $nextNumber);
                    $filled = $answeredQuestions->contains('question_id', $question->id);
                    $answer = $answeredQuestions->firstWhere('question_id', $question->id);

                    $color = '#0080ff'; // Default color
                    if ($answer) {
                        switch ($answer->answer_value) {
                            case 'yakin':
                                $color = '#008000';
                                break;
                            case 'ragu':
                                $color = '#FFA500';
                                break;
                            case 'tidak tahu':
                                $color = '#ff0000';
                                break;
                        }
                    }

                    $filledQuestions[] = [
                        'id' => $question->id,
                        'number' => $question->question_number,
                        'is_fill' => $filled,
                        'is_current' => $isCurrent,
                        'color' => $color
                    ];
                }
            }

            // Return the question numbers along with current number and is_fill status
            return response()->json([
                'error' => false,
                'message' => 'Success get data question number',
                'current_number' => $currentNumber,
                'question_numbers' => $filledQuestions
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetch data Questuon number: ' . $e->getMessage());
            return response()->json(['error' => true, 'message' => 'Error get data question number', 'error_message' => $e->getMessage()], 500);
        }
    }

    public function getQuestionByOrderNumber(Request $request)
    {
        $questionPacketId = $request->input('id');
        if (empty($questionPacketId)){
            return response()->json(['error' => true, 'message' => 'id is required', 'error_message' => 'id is required'], 422);
        }

        $questionNumber = $request->input('number');
        if (empty($questionNumber)){
            return response()->json(['error' => true, 'message' => 'number is required', 'error_message' => 'number is required'], 422);
        }

        try {
            // Find the question packet by its ID
            $questionPacket = QuestionPacket::findOrFail($questionPacketId);

            // Retrieve all the question numbers associated with the question packet
            $question = $questionPacket->questions()->where('question_number', $questionNumber)->with('subtopicList')->orderBy('question_number', 'asc')->first();

            if ($question){
                $studentAnswer = null;
                $studentAnswerValue = null;

                if (Auth::guard('api')->check()) {
                    $user = Auth::guard('api')->user();
                    $answer = $user->studentAnswers()->where('question_packet_id', $questionPacketId)->where('question_id', $question->id)->first();

                    if ($answer) {
                        $studentAnswer = $answer->answer;
                        $studentAnswerValue = $answer->answer_value;
                    }
                }

                $question->student_answer = $studentAnswer;
                $question->student_answer_value = $studentAnswerValue;

                unset($question['question_packet_id']);
                unset($question['subtopic_list_id']);
                unset($question['correct_answer']);
                unset($question['discussion']);
            }

            // Return the question numbers
            return response()->json(['error' => false, 'message' => 'Success get data question', 'data' => $question], 200);
        } catch (\Exception $e) {
            Log::error('Error fetch data Question: ' . $e->getMessage());
            return response()->json(['error' => true, 'message' => 'Error get data question', 'error_message' => $e->getMessage()], 500);
        }
    }

    public function masterDetail(Request $request)
    {
        try {
            $id = $request->input('id');
            if (empty($id)){
                return response()->json(['error' => true, 'message' => 'id is required', 'error_message' => 'id is required'], 422);
            }

            $question_packet = QuestionPacket::with('questions.subtopicList.topic')->where('id', $id)->first();

            return response()->json(['error' => false, 'message' => 'Success get data question packet', 'data' => $question_packet], 200);
        } catch (\Exception $e) {
            Log::error('Error fetch data Educational Status: ' . $e->getMessage());
            return response()->json(['error' => true, 'message' => 'Error get data question packet', 'error_message' => $e->getMessage()], 500);
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
            $hasStarted = StudentToDoList::where('question_packet_id', $request->question_packet_id)
            ->whereNotNull('start_date')
            ->first();

            if ($hasStarted) {
                return response()->json(['error' => true, 'message' => 'The question packet has already started for a student'], 400);
            }

            DB::beginTransaction();
            $questionPacket = QuestionPacket::updateOrCreate(
                ['name' => $request->name],
                ['name' => $request->name, 'is_active' => $request->is_active]
            );

            if (!$questionPacket) {
                return response()->json(['error' => true, 'message' => "Upsert question packet data failed"], 500);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            // Log or handle the exception
            Log::error('Error question packet: ' . $e->getMessage());
            return response()->json(['error' => true, 'message' => 'Error Upsert question packet data', 'error_message' => $e->getMessage()], 500);
        }
        return response()->json(['error' => false, 'message' => 'Upsert question packet data successfully'], 201);
    }

    public function delete(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'question_packet_id' => 'required|integer',
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
            $questionPacket = QuestionPacket::where('id', $request->question_packet_id);
            if (!$questionPacket) {
                return response()->json(['error' => true, 'message' => 'Question packet not found'], 404);
            }

            // Perform the delete operation
            $questionPacket->delete();
            DB::commit();

            return response()->json(['error' => false, 'message' => 'Question packet deleted successfully'], 200);
        } catch (\Exception $e) {
            DB::rollback();
            // Log or handle the exception
            Log::error('Error deleting question packet: ' . $e->getMessage());
            return response()->json(['error' => true, 'message' => 'Error deleting question packet', 'error_message' => $e->getMessage()], 500);
        }
    }

    public function fetch(Request $request)
    {
        try {
            $question_packet = QuestionPacket::leftJoin('student_to_do_list', 'question_packets.id', '=', 'student_to_do_list.question_packet_id')
                ->whereNull('student_to_do_list.question_packet_id')
                ->select('question_packets.*')
                ->get();

            return response()->json(['error' => false, 'message' => 'Success fetch data question packet', 'data' => $question_packet], 200);
        } catch (\Exception $e) {
            Log::error('Error fetch data question packet: ' . $e->getMessage());
            return response()->json(['error' => true, 'message' => 'Error fetch data question packet', 'error_message' => $e->getMessage()], 500);
        }
    }

    public function fetchAll(Request $request)
    {
        try {
            $question_packet = QuestionPacket::get();

            return response()->json(['error' => false, 'message' => 'Success fetch all data question packet', 'data' => $question_packet], 200);
        } catch (\Exception $e) {
            Log::error('Error fetch all data question packet: ' . $e->getMessage());
            return response()->json(['error' => true, 'message' => 'Error fetch all data question packet', 'error_message' => $e->getMessage()], 500);
        }
    }
}
