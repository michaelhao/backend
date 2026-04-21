<?php

namespace App\Enums;

enum BillDetailPaymentType: int
{
    case Monthly = 1;
    case Quarterly = 2;
    case Annual = 3;
}
