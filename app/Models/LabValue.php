<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LabValue extends Model
{
    use HasFactory;

    protected $table = 'lab_values';
    protected $primaryKey = 'id';

    protected $fillable = [
        'category_lab_id',
        'indicator',
        'unit',
        'reference_value'
    ];

    public function categoryLab()
    {
        return $this->belongsTo(CategoryLab::class);
    }
}
