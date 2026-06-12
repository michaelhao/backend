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

    public function getByIdOrFail(int $id): BillDiscount
    {
        return BillDiscount::findOrFail($id);
    }
}
