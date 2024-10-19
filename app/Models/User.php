<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone_number',
        'gender',
        'role',
        'year_of_entry',
        'university_id',
        'educational_status_id',
        'target_exam_date',
        'exam_date_id',
        'change_password',
        'otp_submitted',
        'is_active'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'change_password' => 'boolean',
        'otp_submitted' => 'boolean',
        'is_active' => 'boolean'
    ];

    public function userMembership()
    {
        return $this->hasOne(UserMembership::class);
    }

    public function upgradeMemberships()
    {
        return $this->hasMany(UpgradeMembership::class);
    }

    public function transaction()
    {
        return $this->hasOne(Transaction::class);
    }

    public function studentToDoLists()
    {
        return $this->hasMany(StudentToDoList::class);
    }

    public function studentAnswers()
    {
        return $this->hasMany(StudentAnswer::class);
    }

    public function loginActivity()
    {
        return $this->hasMany(LoginActivity::class);
    }
}
