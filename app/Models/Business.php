<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Business extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'name',
        'business_type',
        'phone',
        'email',
        'address',
        'logo',
        'is_active',
        'created_by',
        'updated_by',
    ];

    public function businesses()
    {
        return $this->belongsTo('users');
    }
}
