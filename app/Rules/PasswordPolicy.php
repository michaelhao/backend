<?php

namespace App\Rules;

use Illuminate\Validation\Rules\Password;

class PasswordPolicy
{
    /**
     * 全站密碼政策；正式環境無對外網路，不使用 uncompromised()（HIBP）。
     */
    public static function default(): Password
    {
        return Password::min(12)->mixedCase()->numbers()->symbols();
    }
}
