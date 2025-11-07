<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $table = 'products';
    public $timestamps = false;
    protected $fillable = ['category_id', 'name', 'description', 'price', 'stock', 'image_url', 'is_available'];

    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }
}
