<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Question extends Model
{
    use HasFactory;

    protected $table = 'questions';
    protected $primaryKey = 'id';

    protected $fillable = [
        'id', 'question_packet_id', 'subtopic_list_id', 'question_number', 'scenario', 'question', 'option_a', 'option_b', 'option_c', 'option_d', 'option_e', 'correct_answer', 'image_url', 'discussion', 'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean'
    ];

    public function questionPacket()
    {
        return $this->belongsTo(QuestionPacket::class);
    }

    public function subtopicList()
    {
        return $this->belongsTo(SubTopicList::class);
    }

    public function studentAnswers()
    {
        return $this->hasManyThrough(StudentAnswer::class, QuestionPacket::class);
    }

}
