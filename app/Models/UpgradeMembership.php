<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UpgradeMembership extends Model
{
    use HasFactory;

    protected $table = 'upgrade_memberships';
    protected $primaryKey = 'id';

    protected $fillable = [
        'user_id',
        'membership_id',
        'status',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function membership()
    {
        return $this->belongsTo(Membership::class);
    }
}
