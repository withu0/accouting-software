<?php

namespace Tests\Unit;

use App\Support\CurlPolyfill;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Tests\TestCase;

class CurlPolyfillTest extends TestCase
{
    #[RunInSeparateProcess]
    public function test_registers_missing_curl_ssl_constants(): void
    {
        if (! extension_loaded('curl')) {
            $this->markTestSkipped('cURL extension is not loaded.');
        }

        CurlPolyfill::register();

        $this->assertTrue(defined('CURL_SSLVERSION_TLSv1_2'));
        $this->assertSame(6, CURL_SSLVERSION_TLSv1_2);
    }
}
