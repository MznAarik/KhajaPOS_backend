<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Item extends Model
{
    protected $fillable = [
        'category_id',
        'name',
        'description',
        'price',
        'image_url',
        'is_available',
        'created_by',
        'updated_by',
    ];

    public function items() {
        return $this->belongsTo(Category::class);
    }
}
