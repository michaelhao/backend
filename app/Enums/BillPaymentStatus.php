<?php

namespace App\Enums;

enum BillPaymentStatus: int
{
    case Pending = 1;
    case Unpaid = 2;
    case Paid = 3;
    case Invalid = 4;

    public function label(): string
    {
        return match ($this) {
            self::Pending => '待審核',
            self::Unpaid => '待付款',
            self::Paid => '已付款',
            self::Invalid => '已失效',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Pending => 'bg-yellow-100 text-yellow-800',
            self::Unpaid => 'bg-orange-100 text-orange-800',
            self::Paid => 'bg-green-100 text-green-800',
            self::Invalid => 'bg-gray-100 text-gray-500',
        };
    }
}
