<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class QuestionsTemplateExport implements WithHeadings, WithMapping
{
    /**
     * Define the headings for the Excel template.
     *
     * @return array
     */
    public function headings(): array
    {
        return [
            'question_packet_id',
            'subtopic_list_id',
            'question_number',
            'scenario',
            'question',
            'option_a',
            'option_b',
            'option_c',
            'option_d',
            'option_e',
            'correct_answer',
            'image_url',
            'discussion',
            'is_active'
        ];
    }

    /**
     * Define the row data for the Excel template.
     *
     * @param mixed $row
     * @return array
     */
    public function map($row): array
    {
        return [
            null, // question_packet_id
            null, // subtopic_list_id
            null, // question_number
            null, // scenario
            null, // question
            null, // option_a
            null, // option_b
            null, // option_c
            null, // option_d
            null, // option_e
            null, // correct_answer
            null, // image_url
            null, // discussion
            null  // is_active
        ];
    }
}
