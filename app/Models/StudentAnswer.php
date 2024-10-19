<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudentAnswer extends Model
{
    use HasFactory;

    protected $table = 'student_answers';
    protected $primaryKey = 'id';

    protected $fillable = [
        'user_id',
        'question_packet_id',
        'question_id',
        'answer',
        'answer_value'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function questionPacket()
    {
        return $this->belongsTo(QuestionPacket::class);
    }

    public function question()
    {
        return $this->belongsTo(Question::class);
    }
}
