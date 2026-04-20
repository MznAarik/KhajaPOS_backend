<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Menu extends Model
{
    use SoftDeletes;
    protected $table = 'menus';
    protected $fillable = [
        'category_id',
        'name',
        'description',
        'price',
        'food_type',
        'image_url',
        'is_available',
        'created_by',
        'updated_by',
    ];

}
