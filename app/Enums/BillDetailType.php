<?php

namespace App\Enums;

enum BillDetailType: int
{
    case Grades = 1;
    case UpgradeFeeDiff = 2;
    case Addons = 3;
    case Discount = 4;

    public function label(): string
    {
        return match ($this) {
            self::Grades => '版本',
            self::UpgradeFeeDiff => '升級補差額',
            self::Addons => '加購功能',
            self::Discount => '折抵',
        };
    }
}
