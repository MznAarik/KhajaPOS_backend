<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RestaurantTable extends Model
{
    protected $table = 'tables';

    protected $fillable = [
        'table_no',
        'qr_code',
        'business_id',
        'is_active',
        'created_by',
        'updated_by',
    ];

    public function orders()
    {
        return $this->hasMany(Order::class, 'table_id');
    }
}
