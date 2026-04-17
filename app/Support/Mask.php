<?php

namespace App\Support;

class Mask
{
    /**
     * Mask odd-index characters of a string with *.
     * e.g. 12345678 → 1*3*5*7*
     */
    public static function string(string $value): string
    {
        $result = '';
        $length = mb_strlen($value);

        for ($i = 0; $i < $length; $i++) {
            $result .= ($i % 2 === 1) ? '*' : mb_substr($value, $i, 1);
        }

        return $result;
    }

    /**
     * Mask odd-index characters in the local part of an email address.
     * e.g. abc@gmail.com → a*c@gmail.com
     *      admin@test.com → a*m*n@test.com
     */
    public static function email(string $email): string
    {
        $atPos = strpos($email, '@');
        if ($atPos === false) {
            return static::string($email);
        }

        return static::string(substr($email, 0, $atPos)).substr($email, $atPos);
    }
}
