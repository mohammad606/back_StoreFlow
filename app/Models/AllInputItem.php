<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AllInputItem extends Model
{
    use HasFactory;

    protected $fillable = ['all_input_id', 'product_id', 'name', 'quantity'];

    public function allInput()
    {
        return $this->belongsTo(AllInput::class);
    }

    public function product()
    {
        return $this->belongsTo(Store::class, 'product_id');
    }
}
