<?php

if (! function_exists('maskEmail')) {
    function maskEmail(string $email): string
    {
        return \App\Support\Mask::email($email);
    }
}

if (! function_exists('maskString')) {
    function maskString(string $value): string
    {
        return \App\Support\Mask::string($value);
    }
}
