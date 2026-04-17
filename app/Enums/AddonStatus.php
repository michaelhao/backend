<?php

namespace App\Enums;

enum AddonStatus: int
{
    case Active = 1;
    case Inactive = 0;
    case Deleted = -1;
}
