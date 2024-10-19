<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubTopicList extends Model
{
    use HasFactory;

    protected $table = 'subtopic_lists';
    protected $primaryKey = 'id';

    protected $fillable = [
        'topic_id',
        'subtopic',
        'competence',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean'
    ];

    public function topic()
    {
        return $this->belongsTo(TopicList::class);
    }
}
