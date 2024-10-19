<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudentToDoList extends Model
{
    use HasFactory;

    protected $table = 'student_to_do_list';
    protected $primaryKey = 'id';

    protected $fillable = [
        'user_id',
        'question_packet_id',
        'start_date',
        'finish_date',
        'score',
        'is_done'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_done' => 'boolean'
    ];

    public function questionPacket()
    {
        return $this->belongsTo(QuestionPacket::class, 'question_packet_id');
    }
}
