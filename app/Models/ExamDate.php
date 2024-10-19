<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExamDate extends Model
{
    use HasFactory;

    protected $table = 'exam_dates';
    protected $primaryKey = 'id';

    protected $fillable = [
        'date',
        'is_active'
    ];

    protected $casts = [
        'date' => 'datetime',
        'is_active' => 'boolean'
    ];
}
