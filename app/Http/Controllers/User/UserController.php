<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\QuestionPacket;
use App\Models\Question;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    private function calculateRankingScore($answer_value, $is_correct)
    {
        // Assign a numerical value to answer_value
        $answer_scores = [
            'yakin' => 1.0,
            'ragu' => 0.5,
            'tidak tahu' => 0.0
        ];

        // Default score for unknown values
        $confidence_score = isset($answer_scores[$answer_value]) ? $answer_scores[$answer_value] : 0;

        // Calculate final score: if correct, confidence score applies, otherwise it's negative
        return $is_correct ? $confidence_score : -$confidence_score;
    }
    public function index(Request $request)
    {
        try {
            $perPage = $request->input('per_page', 10); // Set a default per page value
            $sortBy = $request->input('sort_by', 'name'); // Sort by name by default
            $sortOrder = $request->input('sort_order', 'asc'); // Sort in ascending order by default

            $user = User::orderBy($sortBy, $sortOrder)->paginate($perPage);

            return response()->json(['error' => false, 'message' => 'Success fetch data User', 'User' => $user], 200);
        } catch (\Exception $e) {
            Log::error('Error fetch data User: ' . $e->getMessage());
            return response()->json(['error' => true, 'message' => 'Error fetch data User', 'error_message' => $e->getMessage()], 500);
        }
    }

    public function detail(Request $request)
    {
        try {
            $id = $request->input('id');
            if (empty($id)){
                return response()->json(['error' => true, 'message' => 'id is required', 'error_message' => 'id is required'], 422);
            }

            $user = User::find($id);

            return response()->json(['error' => false, 'message' => 'Success get data User', 'User' => $user], 200);
        } catch (\Exception $e) {
            Log::error('Error fetch data Educational Status: ' . $e->getMessage());
            return response()->json(['error' => true, 'message' => 'Error get data User', 'error_message' => $e->getMessage()], 500);
        }
    }

    public function upsert(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'old_name' => 'string|max:255',
            'name' => 'required|string|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => true, 'message' => $validator->errors()], 422);
        }

        try {
            DB::beginTransaction();
            $user = User::updateOrCreate(
                ['name' => $request->old_name],
                ['name' => $request->name]
            );

            if (!$user) {
                return response()->json(['error' => true, 'message' => "Upsert User data failed"], 500);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            // Log or handle the exception
            Log::error('Error User: ' . $e->getMessage());
            return response()->json(['error' => true, 'message' => 'Error Upsert User data', 'error_message' => $e->getMessage()], 500);
        }
        return response()->json(['error' => false, 'message' => 'Upsert User data successfully'], 201);
    }

    public function doTheTest(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'question_packet_id' => [
                'required',
                'integer',
                Rule::exists('question_packets', 'id')
            ]
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => true, 'errors' => $validator->errors()], 422);
        }

        try {
            DB::beginTransaction();

            $user = Auth::guard('api')->user()->load('userMembership')->load('userMembership.membership');
            if (!$user) {
                return response()->json(['error' => true, 'message' => 'Unauthorized'], 401);
            }

            $existingToDoList = $user->studentToDoLists()->where('question_packet_id', $request->question_packet_id)->first();
            // If no existing record found, create a new one
            if (!$existingToDoList) {
                $user->studentToDoLists()->create([
                    'question_packet_id' => $request->question_packet_id,
                    'start_date' => Carbon::now(),
                    'score' => 0,
                    'is_done' => false,
                ]);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();

            Log::error('Error fetch data Educational Status: ' . $e->getMessage());
            return response()->json(['error' => true, 'message' => 'Error create user to do list', 'error_message' => $e->getMessage()], 500);
        }

        return response()->json(['error' => false, 'message' => 'Success create user to do list', 'data' => null], 200);
    }

    public function reviewAnswer(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'question_packet_id' => [
                'required',
                'integer',
                Rule::exists('question_packets', 'id')
            ],
            'number' => [
                'required',
                'integer'
            ]
        ]);
        if ($validator->fails()) {
            return response()->json(['error' => true, 'errors' => $validator->errors()], 422);
        }

        try {
            DB::beginTransaction();

            $user = Auth::guard('api')->user()->load('userMembership')->load('userMembership.membership');
            if (!$user) {
                return response()->json(['error' => true, 'message' => 'Unauthorized'], 401);
            }

            $isDone = $user->studentToDoLists()->where('question_packet_id', $request->question_packet_id)->value('is_done');
            if (!$isDone) {
                return response()->json(['error' => true, 'message' => 'Test not finished yet'], 403);
            }

            $questionNumber = $request->input('number');
            $question = Question::where('question_packet_id', $request->question_packet_id)
                        ->where('question_number', $questionNumber)
                        ->first();
            if (!$question) {
                return response()->json(['error' => true, 'message' => 'Question not found'], 404);
            }

            $userAnswer = $user->studentAnswers->where('question_id', $question->id)->first();

            // Return the user's answer value for the specified question number
            return response()->json([
                'error' => false,
                'message' => 'Success get data correct answer and answer value ' . $questionNumber,
                'data' => [
                    'question_id' => $question->id,
                    'question_number' => $question->question_number,
                    'correct_answer' => $question->correct_answer,
                    'discussion' => $question->discussion,
                    'user_answer' => $userAnswer ? $userAnswer->answer : null,
                    'user_answer_level' => $userAnswer ? $userAnswer->answer_value : null
                ]
            ], 200);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();

            Log::error('Error get data correct answer and answer value: ' . $e->getMessage());
            return response()->json(['error' => true, 'message' => 'Error get data correct answer and answer value', 'error_message' => $e->getMessage()], 500);
        }
    }

    public function finishTheTest(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'question_packet_id' => [
                'required',
                'integer',
                Rule::exists('question_packets', 'id')
            ]
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => true, 'errors' => $validator->errors()], 422);
        }

        try {
            DB::beginTransaction();

            $user = Auth::guard('api')->user()->load('userMembership')->load('userMembership.membership');
            if (!$user) {
                return response()->json(['error' => true, 'message' => 'Unauthorized'], 401);
            }

            $correctAnswer = 0;

            $questionPacket = QuestionPacket::with('questions.subtopicList.topic')->where('id', $request->question_packet_id)->first();
            $userAnswered = $user->studentAnswers->where('question_packet_id', $request->question_packet_id);

            foreach($userAnswered as $student){
                $question = Question::where('id', $student->question_id)->first();
                $is_correct = $student->answer == $question->correct_answer;
                if($is_correct){
                    $correctAnswer++;
                }

                $ranking_score = $this->calculateRankingScore($student->answer_value, $is_correct);

                // Store or process the ranking score
                $student->ranking_score = $ranking_score;
                $student->save();
            }

            $totalQuestion = $questionPacket->questions->count();
            $score = ($correctAnswer/$totalQuestion)*100;

            $user->studentToDoLists()->where('question_packet_id', $request->question_packet_id)->update(
                [
                    'finish_date' => Carbon::now(),
                    'score' => $score,
                    'is_done' => true,
                ]
            );

            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();

            Log::error('Error finish the test: ' . $e->getMessage());
            return response()->json(['error' => true, 'message' => 'Error finish the test', 'error_message' => $e->getMessage()], 500);
        }

        return response()->json(['error' => false, 'message' => 'Success finish the test', 'data' => ["score" => $score, "answered_question" => count($userAnswered), "correct_answer" => $correctAnswer]], 200);
    }

    public function testReview(Request $request)
    {
        $questionPacketId = $request->input('id');
        if (empty($questionPacketId)){
            return response()->json(['error' => true, 'message' => 'id is required', 'error_message' => 'id is required'], 422);
        }

        try {
            DB::beginTransaction();

            $user = Auth::guard('api')->user()->load('userMembership')->load('userMembership.membership');
            if (!$user) {
                return response()->json(['error' => true, 'message' => 'Unauthorized'], 401);
            }

            $correctAnswer = 0;

            $questionPacket = QuestionPacket::with('questions.subtopicList.topic')->where('id', $questionPacketId)->first();
            $userAnswered = $user->studentAnswers->where('question_packet_id', $questionPacketId);

            foreach($userAnswered as $student){
                $question = Question::where('id', $student->question_id)->first();
                if($student->answer == $question->correct_answer){
                    $correctAnswer++;
                }
            }

            $totalQuestion = $questionPacket->questions->count();
            $totalAnswered = $userAnswered->count();
            $totalSkip = $totalQuestion - $totalAnswered;

            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();

            Log::error('Error finish the test: ' . $e->getMessage());
            return response()->json(['error' => true, 'message' => 'Error review the test', 'error_message' => $e->getMessage()], 500);
        }

        return response()->json(['error' => false, 'message' => 'Success review the test', 'data' => ["total_answered" => $totalAnswered, "total_skip" => $totalSkip, "total_question" => $totalQuestion]], 200);
    }

    public function testResult(Request $request)
    {
        $questionPacketId = $request->input('id');
        if (empty($questionPacketId)){
            return response()->json(['error' => true, 'message' => 'id is required', 'error_message' => 'id is required'], 422);
        }

        try {
            DB::beginTransaction();

            $user = Auth::guard('api')->user()->load('userMembership')->load('userMembership.membership');
            if (!$user) {
                return response()->json(['error' => true, 'message' => 'Unauthorized'], 401);
            }

            $correctAnswer = 0;

            $questionPacket = QuestionPacket::with('questions.subtopicList.topic')->where('id', $questionPacketId)->first();
            $userAnswered = $user->studentAnswers->where('question_packet_id', $questionPacketId);

            $totalQuestion = $questionPacket->questions->count();
            $totalAnswered = $userAnswered->count();
            $totalSkip = $totalQuestion - $totalAnswered;

            foreach($userAnswered as $student){
                $question = Question::where('id', $student->question_id)->first();
                if($student->answer == $question->correct_answer){
                    $correctAnswer++;
                }
            }

            $totalQuestion = $questionPacket->questions->count();
            $score = ($correctAnswer/$totalQuestion)*100;

            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();

            Log::error('Error finish the test: ' . $e->getMessage());
            return response()->json(['error' => true, 'message' => 'Error result the test', 'error_message' => $e->getMessage()], 500);
        }

        return response()->json(['error' => false, 'message' => 'Success result the test', 'data' => ["total_answered" => $totalAnswered, "total_skip" => $totalSkip, "total_question" => $totalQuestion, "score" => $score]], 200);
    }

    public function userSkipQuestions(Request $request)
    {
        $questionPacketId = $request->input('id');
        if (empty($questionPacketId)){
            return response()->json(['error' => true, 'message' => 'id is required', 'error_message' => 'id is required'], 422);
        }

        try {
            DB::beginTransaction();

            $user = Auth::guard('api')->user()->load('userMembership')->load('userMembership.membership');
            if (!$user) {
                return response()->json(['error' => true, 'message' => 'Unauthorized'], 401);
            }

            $questionPacket = QuestionPacket::with('questions.subtopicList.topic')->where('id', $questionPacketId)->first();

            // Retrieve all questions in the packet
            $allQuestions = $questionPacket->questions->pluck('id');

            // Retrieve the IDs of questions that the user has answered
            $answeredQuestionIds = $user->studentAnswers->where('question_packet_id', $questionPacketId)->pluck('question_id');

            // Find the IDs of questions that the user has not answered
            $skippedQuestionIds = $allQuestions->diff($answeredQuestionIds);

            // Retrieve details of skipped questions
            $skippedQuestions = Question::whereIn('id', $skippedQuestionIds)
                                    ->orderBy('question_number', 'asc')
                                    ->get();

            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();

            Log::error('Error finish the test: ' . $e->getMessage());
            return response()->json(['error' => true, 'message' => 'Error get the skip question', 'error_message' => $e->getMessage()], 500);
        }

        return response()->json(['error' => false, 'message' => 'Success get the skip question', 'data' => $skippedQuestions], 200);
    }

    public function studentAnswer(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'question_packet_id' => [
                'required',
                'integer',
                Rule::exists('question_packets', 'id')
            ],
            'question_id' => [
                'required',
                'integer',
                Rule::exists('questions', 'id')
            ],
            'answer' => [
                'required',
                'string',
                'in:A,B,C,D,E',
            ],
            'answer_value' => [
                'required',
                'string',
                'in:yakin,ragu,tidak tahu',
            ],
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => true, 'errors' => $validator->errors()], 422);
        }

        try {
            DB::beginTransaction();

            $user = Auth::guard('api')->user()->load('userMembership')->load('userMembership.membership');
            if (!$user) {
                return response()->json(['error' => true, 'message' => 'Unauthorized'], 401);
            }

            $existingToDoList = $user->studentToDoLists()->where('question_packet_id', $request->question_packet_id)->first();
            // If no existing record found, create a new one
            if (!$existingToDoList) {
                $user->studentToDoLists()->create([
                    'question_packet_id' => $request->question_packet_id,
                    'start_date' => Carbon::now(),
                    'score' => 0,
                    'is_done' => false,
                ]);
            }

            $user->studentAnswers()->updateOrCreate(
                [
                    'question_packet_id' => $request->question_packet_id,
                    'question_id' => $request->question_id,
                ],
                [
                    'answer' => $request->answer,
                    'answer_value' => $request->answer_value,
                ]
            );


            // Fetch the next question number
            $nextQuestion = Question::where('id', '>', $request->question_id)
                ->where('question_packet_id', $request->question_packet_id)
                ->orderBy('question_number')
                ->first();

            $nextNumber = $nextQuestion ? $nextQuestion->question_number : null;

             // Fetch the previous answer's number
            $previousAnswer = Question::where('id', '<=', $request->question_id)
                ->where('question_packet_id', $request->question_packet_id)
                ->orderByDesc('question_number')
                ->first();

            $beforeNumber = $previousAnswer ? $previousAnswer->question_number : null;

            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();

            Log::error('Error fetch data Educational Status: ' . $e->getMessage());
            return response()->json(['error' => true, 'message' => 'Error create user answer', 'error_message' => $e->getMessage()], 500);
        }

        return response()->json(['error' => false, 'message' => 'Success create user answer', 'data' => [
            "before_number" => $beforeNumber,
            "next_number" => $nextNumber
        ]], 200);
    }

    public function countdownExamDate(Request $request)
    {
        try {
            $user = Auth::guard('api')->user();
            if (!$user) {
                return response()->json(['error' => true, 'message' => 'Unauthorized'], 401);
            }

            // Parse the target exam date using Carbon
            $targetExamDate = Carbon::parse($user->target_exam_date);
            // Get the current date
            $currentDate = Carbon::now();
            $countdown = 0;

            // Calculate the difference
            if (!$currentDate->greaterThanOrEqualTo($targetExamDate)) {
                $difference = $currentDate->diff($targetExamDate);
                $countdown = $difference->days;
            }
        } catch (\Exception $e) {
            Log::error('Error count exam date: ' . $e->getMessage());
            return response()->json(['error' => true, 'message' => 'Error count down exam date', 'error_message' => $e->getMessage()], 500);
        }

        return response()->json(['error' => false, 'message' => 'Success count down exam date', 'data' => [
            "count_down" => $countdown
        ]], 200);
    }

    public function getNeedToLearnTopics(Request $request)
    {
        try{
            $user = Auth::guard('api')->user();
            if (!$user) {
                return response()->json(['error' => true, 'message' => 'Unauthorized'], 401);
            }

            // Fetch the topics with many wrong answers
            $topicsWithWrongAnswers = DB::table(DB::raw('(SELECT questions.subtopic_list_id, COUNT(student_answers.id) as wrong_answers_count
                                            FROM student_answers
                                            INNER JOIN questions ON student_answers.question_id = questions.id
                                            WHERE student_answers.user_id = ? AND student_answers.answer != questions.correct_answer
                                            GROUP BY questions.subtopic_list_id) as subquery'))
                ->join('subtopic_lists', 'subquery.subtopic_list_id', '=', 'subtopic_lists.id')
                ->join('topic_lists', 'subtopic_lists.topic_id', '=', 'topic_lists.id')
                ->select('topic_lists.topic as title', 'subtopic_lists.subtopic as subtitle', 'subquery.wrong_answers_count')
                ->orderBy('subquery.wrong_answers_count', 'desc')
                ->setBindings([$user->id])
                ->get();

            return response()->json(['error' => false, 'message' => 'Success fetch learning topics', 'data' => $topicsWithWrongAnswers], 200);
        } catch (\Exception $e) {
            Log::error('Error fetch topic: ' . $e->getMessage());
            return response()->json(['error' => true, 'message' => 'Error fetch topic', 'error_message' => $e->getMessage()], 500);
        }
    }

    public function userTrialReport(Request $request)
    {
        try {
            $perPage = $request->input('per_page', 10);
            $search = $request->input('search', '');

            // Fetch users with related data, filtered by search criteria
            $datas = User::whereHas('userMembership', function ($query) {
                $query->where('membership_id', 1);
            })
            ->where(function ($query) use ($search) {
                $query->where('id', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            })
            ->with([
                'loginActivity' => function($query) {
                    $query->orderBy('login_at', 'desc');
                },
                'upgradeMemberships',
                'studentToDoLists' => function($query) {
                    $query->where('question_packet_id', 7)
                        ->with('questionPacket'); // Include the question packet relationship
                },
                'userMembership' => function($query) {
                    $query->orderBy('end_date_activation', 'desc');
                }
            ])
            ->paginate($perPage);

            $datas->getCollection()->transform(function ($data) {
                // Format created date
                $data->created_date = Carbon::parse($data->created_at)->format('d F Y H:i:s');

                // Map login activities
                $data->login_activities = $data->loginActivity->map(function($activity) {
                    return Carbon::parse($activity->login_at)->format('d F Y H:i:s');
                });

                // Map upgrade memberships
                $data->upgrade_membership_journey = $data->upgradeMemberships->map(function($membership) {
                    return [
                        'membership_id' => $membership->membership_id,
                        'status' => $membership->status,
                    ];
                });

                // Map student todo lists for trial packets
                $data->trial_packet_completion_date = $data->studentToDoLists->sortByDesc('created_at')->first()?->created_at
                ? Carbon::parse($data->studentToDoLists->sortByDesc('created_at')->first()->created_at)->format('d F Y H:i:s')
                : null;

                $data->studentToDoLists->map(function($todo) {
                    return [
                        'id' => $todo->id,
                        'question_packet_id' => $todo->question_packet_id,
                        'question_packet_name' => $todo->questionPacket->name, // Assuming 'name' is the field for the packet name
                        'start_date' => $todo->start_date,
                        'finish_date' => $todo->finish_date,
                        'score' => $todo->score,
                        'is_done' => $todo->is_done,
                    ];
                });

                // Set flags for membership levels
                $data->is_user_trial = $data->userMembership->membership_id === "1";
                $data->is_user_silver = $data->userMembership->membership_id === "2";
                $data->is_user_gold = $data->userMembership->membership_id === "3";

                return $data;
            });

            return response()->json(['error' => false, 'message' => 'Success fetch data user trial report', 'data' => $datas], 200);
        } catch (\Exception $e) {
            Log::error('Error fetch data user trial report: ' . $e->getMessage());
            return response()->json(['error' => true, 'message' => 'Error fetch data user trial report', 'error_message' => $e->getMessage()], 500);
        }
    }

    public function userPremiumReport(Request $request)
    {
        try {
            $perPage = $request->input('per_page', 10);
            $search = $request->input('search', '');

            // Fetch premium users (membership_id != 1) with related data, filtered by search criteria
            $datas = User::whereHas('userMembership', function ($query) {
                $query->where('membership_id', '!=', 1); // Only premium users
            })
            ->where(function ($query) use ($search) {
                $query->where('id', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            })
            ->with([
                'loginActivity' => function($query) {
                    $query->orderBy('login_at', 'desc');
                },
                'upgradeMemberships',
                'studentToDoLists' => function($query) {
                    $query->where('question_packet_id', 7)
                        ->with('questionPacket'); // Include the question packet relationship
                },
                'userMembership' => function($query) {
                    $query->orderBy('end_date_activation', 'desc');
                }
            ])
            ->paginate($perPage);

            // Data transformation and additional fields formatting
            $datas->getCollection()->transform(function ($data) {
                $data->created_date = Carbon::parse($data->created_at)->format('d F Y H:i:s');
                $data->login_activities = $data->loginActivity->map(function($activity) {
                    return Carbon::parse($activity->login_at)->format('d F Y H:i:s');
                });

                $data->upgrade_membership_journey = $data->upgradeMemberships->map(function($membership) {
                    return [
                        'membership_id' => $membership->membership_id,
                        'status' => $membership->status,
                    ];
                });

                $data->trial_packet_completion_date = $data->studentToDoLists->sortByDesc('created_at')->first()?->created_at
                ? Carbon::parse($data->studentToDoLists->sortByDesc('created_at')->first()->created_at)->format('d F Y H:i:s')
                : null;

                $data->studentToDoLists->map(function($todo) {
                    return [
                        'id' => $todo->id,
                        'question_packet_id' => $todo->question_packet_id,
                        'question_packet_name' => $todo->questionPacket->name,
                        'start_date' => $todo->start_date,
                        'finish_date' => $todo->finish_date,
                        'score' => $todo->score,
                        'is_done' => $todo->is_done,
                    ];
                });

                $data->is_user_trial = $data->userMembership->membership_id === "1";
                $data->is_user_silver = $data->userMembership->membership_id === "2";
                $data->is_user_gold = $data->userMembership->membership_id === "3";

                return $data;
            });

            return response()->json(['error' => false, 'message' => 'Success fetch data user premium report', 'data' => $datas], 200);
        } catch (\Exception $e) {
            Log::error('Error fetch data user premium report: ' . $e->getMessage());
            return response()->json(['error' => true, 'message' => 'Error fetch data user premium report', 'error_message' => $e->getMessage()], 500);
        }
    }

}
