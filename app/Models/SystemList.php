<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemList extends Model
{
    use HasFactory;

    protected $table = 'system_lists';
    protected $primaryKey = 'id';

    protected $fillable = [
        'topic',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean'
    ];

    public function topicLists()
    {
        return $this->hasMany(TopicList::class);
    }
}
