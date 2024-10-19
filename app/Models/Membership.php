<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Membership extends Model
{
    use HasFactory;

    protected $table = 'memberships';
    protected $primaryKey = 'id';

    protected $fillable = [
        'name',
        'class',
        'description',
        'image_url',
        'price',
        'activation_period',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean'
    ];

    public function userMembership()
    {
        return $this->hasOne(UserMembership::class);
    }

    public function transaction()
    {
        return $this->hasOne(Transaction::class);
    }
}
