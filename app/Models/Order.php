<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $table = 'orders';
    public $timestamps = false;
    protected $fillable = ['client_id', 'seller_id', 'delivery_address', 'total_amount', 'status', 'payment_method', 'created_at', 'updated_at'];

    public function items()
    {
        return $this->hasMany(OrderItem::class, 'order_id');
    }

    public function client()
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id');
    }
}
