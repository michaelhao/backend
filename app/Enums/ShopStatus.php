<?php

namespace App\Enums;

enum ShopStatus: int
{
    case Active   = 1;   // 啟用
    case Closed   = 0;   // 關閉
    case Expired  = -1;  // 過期
    case Archived = -2;  // 封存
}
