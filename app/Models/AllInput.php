<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AllInput extends Model
{
    use HasFactory;

    protected $fillable = ['date', 'type', 'noa', 'customer_id','user_id', 'customer_name'];

    public function items()
    {
        return $this->hasMany(AllInputItem::class, 'all_input_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
