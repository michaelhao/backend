<?php

namespace App\Repositories;

use App\Models\BillDiscount;
use Illuminate\Database\Eloquent\Collection;

class BillDiscountRepository
{
    public function getAllOrdered(): Collection
    {
        return BillDiscount::orderBy('id')->get();
    }

    public function getById(int $id): ?BillDiscount
    {
        return BillDiscount::find($id);
    }
}
