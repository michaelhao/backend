<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BillDiscount extends Model
{
    protected $table = 'bills_discount';

    protected $fillable = ['name', 'description'];
}
