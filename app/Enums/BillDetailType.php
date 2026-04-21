<?php

namespace App\Enums;

enum BillDetailType: int
{
    case Grades = 1;
    case UpgradeFeeDiff = 2;
    case Addons = 3;
    case Discount = 4;
}
