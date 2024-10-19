<?php

namespace App\Imports;

use App\Models\Question;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Validators\Failure;

class QuestionsImport implements ToCollection, WithHeadingRow, SkipsOnFailure
{
    use SkipsFailures;

    /**
     * @param Collection $rows
     */
    public function collection(Collection $rows)
    {
        foreach ($rows as $row) {
            $data = $row->toArray();

            $validator = Validator::make($data, [
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
                'is_active' => 'required|boolean'
            ]);

            if ($validator->fails()) {
                foreach ($validator->errors()->getMessages() as $attribute => $errors) {
                    $this->failures[] = new Failure(
                        $row->get('question_number'), // row number
                        $attribute, // attribute that failed
                        $errors, // validation errors
                        $data // row values
                    );
                }
                continue;
            }

            Question::updateOrCreate(
                [
                    'question_packet_id' => $data['question_packet_id'],
                    'subtopic_list_id' => $data['subtopic_list_id'],
                    'question_number' => $data['question_number']
                ],
                [
                    'scenario' => $data['scenario'],
                    'question' => $data['question'],
                    'option_a' => $data['option_a'],
                    'option_b' => $data['option_b'],
                    'option_c' => $data['option_c'],
                    'option_d' => $data['option_d'],
                    'option_e' => $data['option_e'],
                    'correct_answer' => $data['correct_answer'],
                    'image_url' => $data['image_url'],
                    'discussion' => $data['discussion'],
                    'is_active' => $data['is_active']
                ]
            );
        }
    }
}
