<?php

namespace App\Enums;

enum BillPaymentStatus: int
{
    case Pending = 1;
    case Unpaid = 2;
    case Paid = 3;
    case Invalid = 4;
}
