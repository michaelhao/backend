<?php

namespace App\Enums;

enum BillPaymentMethod: int
{
    case CreditCard = 1;
    case Transfer = 2;
    case Cash = 3;
}
