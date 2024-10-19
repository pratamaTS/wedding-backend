<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TopicList extends Model
{
    use HasFactory;

    protected $table = 'topic_lists';
    protected $primaryKey = 'id';

    protected $fillable = [
        'system_id',
        'topic',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean'
    ];

    public function systemList()
    {
        return $this->belongsTo(SystemList::class, 'system_id');
    }
}
