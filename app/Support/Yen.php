<?php

namespace App\Support;

class Yen
{
    public static function format(int $amount): string
    {
        $formatted = number_format(abs($amount));

        return $amount < 0 ? '△'.$formatted : $formatted;
    }
}
