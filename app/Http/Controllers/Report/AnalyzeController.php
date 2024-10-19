<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\QuestionPacket;
use App\Models\StudentToDoList;

class AnalyzeController extends Controller
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

        // If incorrect, apply a reduction factor instead of making it negative
        return $is_correct ? $confidence_score : $confidence_score * 0.1;
    }

    private function calculateRankingScoreByRekuensi($rekurensi, $jumlah_test, $kompetensi)
    {
        return $rekurensi * $jumlah_test * $kompetensi;
    }

    public function analyzeResult(Request $request)
    {
        $user = Auth::guard('api')->user()->load('studentToDoLists')->load('userMembership.membership');
        if (!$user) {
            return response()->json(['error' => true, 'message' => 'Unauthorized'], 401);
        }

        $answers = $user->studentAnswers()->with([
                        'questionPacket',
                        'question.subtopicList.topic.systemList'
                    ])->get();
        $title = "Analisis";
        $question_packets = $answers->pluck('questionPacket.name')->toArray();
        if (count($question_packets) >= 2) {
            $first_packet = $question_packets[0];
            $last_packet = $question_packets[count($question_packets) - 1];
            $title = "Analisis $first_packet sd. $last_packet";
        }

        $diagnoses = [];
        $topics = [];
        $rankings = [];

        $advise_learn_by_diagnose = [];
        $advise_learn_by_topic = [];

        foreach ($answers as $answer) {
            $is_correct = $answer->answer == $answer->question->correct_answer;
            $confidence_score = $this->calculateRankingScore($answer->answer_value, $is_correct);

            // Safely check if subtopic and topic exist
            $subTopic = isset($answer->question->subtopicList->subtopic) ? $answer->question->subtopicList->subtopic : null;
            $topic = isset($answer->question->subtopicList->topic) ? $answer->question->subtopicList->topic->topic : null;
            $system = isset($answer->question->subtopicList->topic->systemList) ? $answer->question->subtopicList->topic->systemList->topic : null;


            if (!isset($rankings[$topic])) {
                $rankings[$topic] = [
                    'rekurensi' => 0,
                    'jumlah_test' => 0,
                    'kompetensi' => []
                ];
            }

            // Increment rekursi and jumlah_test
            $rankings[$topic]['rekurensi'] += 1;
            $rankings[$topic]['jumlah_test'] += 1;
            $rankings[$topic]['kompetensi'][] = $confidence_score;

            // Group by diagnose and topic for advising
            $advise_learn_by_diagnose[$subTopic][] = [
                'group' => $topic,
                'score' => $confidence_score
            ];

            $advise_learn_by_topic[$topic][] = [
                'group' => $system,
                'score' => $confidence_score,
            ];
        }

        // Calculate the final ranking score for each topic
        foreach ($rankings as $topic => &$data) {
            $average_kompetensi = array_sum($data['kompetensi']) / count($data['kompetensi']);
            $data['ranking_score'] = $this->calculateRankingScoreByRekuensi($data['rekurensi'], $data['jumlah_test'], $average_kompetensi);
        }

        // Sort by ranking score in descending order
        usort($rankings, function ($a, $b) {
            return $b['ranking_score'] <=> $a['ranking_score'];
        });

        // Sort advise_learn_by_diagnose by score ascending
        foreach ($advise_learn_by_diagnose as $diagnose => &$questions) {
            usort($questions, function ($a, $b) {
                return $a['score'] <=> $b['score'];
            });
        }

        // Sort advise_learn_by_topic by score ascending
        foreach ($advise_learn_by_topic as $topic => &$questions) {
            usort($questions, function ($a, $b) {
                return $a['score'] <=> $b['score'];
            });
        }

        return response()->json([
            'error' => false,
            'message' => 'Success get result data',
            'data' => [
                'title' => $title,
                'description' => 'Kesimpulan dari hasil analisan dalam pengerjaan 3 paket soal',
                'advise_based_on_diagnose' => $rankings,
                'advise_learn_by_diagnose' => $advise_learn_by_diagnose,
                'advise_learn_by_topic' => $advise_learn_by_topic
            ]
        ]);
    }

    public function analyzeProgress(Request $request)
    {
        $user = Auth::guard('api')->user()->load('studentToDoLists')->load('userMembership.membership');
        if (!$user) {
            return response()->json(['error' => true, 'message' => 'Unauthorized'], 401);
        }
        $questionPackets = $user->studentToDoLists->with('questionPacket', 'questionPacket.questions')
                                ->take(3)
                                ->get();

        $progress = [];

        foreach ($questionPackets as $packet) {
            $studentScore = $packet->score;
            $limitScore = $packet->questionPacket->questions->sum('limit_score');
            $averageScore = StudentToDoList::where('student_id', '!=', $studentId)
                                ->avg('score');

            $progress[] = [
                'title' => $packet->questionPacket->name,
                'student_score' => $studentScore,
                'limit_score' => $limitScore,
                'rate_student_score' => $averageScore
            ];
        }

        return response()->json([
            'error' => false,
            'message' => 'Success get progress data',
            'data' => $progress
        ]);
    }

    public function analyzePercentage(Request $request)
    {
        $studentId = auth()->user()->id; // Assuming the user is authenticated
        $questionPackets = StudentToDoList::with('questionPacket', 'questionPacket.questions')
                                ->where('student_id', $studentId)
                                ->take(3)
                                ->get();

        $percentageData = [];

        foreach ($questionPackets as $packet) {
            $studentScore = $packet->score;
            $limitScore = $packet->questionPacket->questions->sum('limit_score');
            $successPercentage = ($studentScore / $limitScore) * 100;

            $status = ($successPercentage >= 65)
                    ? 'Memenuhi Nilai Batas Kelulusan'
                    : 'Belum Memenuhi Nilai Batas Kelulusan';

            $percentageData[] = [
                'title' => $packet->questionPacket->name,
                'success_percentage' => number_format($successPercentage, 2) . '% (' . $status . ')'
            ];
        }

        return response()->json([
            'error' => false,
            'message' => 'Success get percentage data',
            'data' => $percentageData
        ]);
    }

    public function analyzeAdvis(Request $request)
    {
        $studentId = auth()->user()->id; // Assuming the user is authenticated
        $questionPackets = StudentToDoList::with('questionPacket', 'questionPacket.questions')
                                ->where('student_id', $studentId)
                                ->take(3)
                                ->get();

        $totalScore = 0;
        $totalLimitScore = 0;

        foreach ($questionPackets as $packet) {
            $totalScore += $packet->score;
            $totalLimitScore += $packet->questionPacket->questions->sum('limit_score');
        }

        $finalPercentage = ($totalScore / $totalLimitScore) * 100;
        $success = $finalPercentage >= 65;

        $conclusion = $success
            ? 'Selamat! Anda telah memenuhi nilai batas kelulusan. Lanjutkan belajar dan kembangkan pemahaman.'
            : 'Hasil 3 tryout Anda belum mencapai batas kelulusan. Diperlukan pembelajaran intensif lebih lanjut.';

        return response()->json([
            'error' => false,
            'message' => 'Success get advis data',
            'data' => [
                'success' => $success,
                'conclusion' => $conclusion
            ]
        ]);
    }

}
