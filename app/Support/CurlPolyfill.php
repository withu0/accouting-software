<?php

namespace App\Support;

final class CurlPolyfill
{
    public static function register(): void
    {
        if (! extension_loaded('curl')) {
            return;
        }

        $constants = [
            'CURL_SSLVERSION_DEFAULT' => 0,
            'CURL_SSLVERSION_TLSv1' => 1,
            'CURL_SSLVERSION_SSLv2' => 2,
            'CURL_SSLVERSION_SSLv3' => 3,
            'CURL_SSLVERSION_TLSv1_0' => 4,
            'CURL_SSLVERSION_TLSv1_1' => 5,
            'CURL_SSLVERSION_TLSv1_2' => 6,
            'CURL_SSLVERSION_TLSv1_3' => 7,
        ];

        foreach ($constants as $name => $value) {
            if (! defined($name)) {
                define($name, $value);
            }
        }
    }
}
