<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CategoryLab extends Model
{
    use HasFactory;

    protected $table = 'categories_lab';
    protected $primaryKey = 'id';

    protected $fillable = [
        'name'
    ];
}
