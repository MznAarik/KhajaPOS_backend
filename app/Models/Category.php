<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class categories extends Model
{
    protected $fillable = [
        'name',
        'description',
        'is_active',
        'created_at',
        'updated_at',
    ];

}
