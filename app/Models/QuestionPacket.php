<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuestionPacket extends Model
{
    use HasFactory;

    protected $table = 'question_packets';
    protected $primaryKey = 'id';

    protected $fillable = [
        'name',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean'
    ];

    public function questions()
    {
        return $this->hasMany(Question::class);
    }

    public function studentAnswers()
    {
        return $this->hasManyThrough(StudentAnswer::class, Question::class);
    }

}
