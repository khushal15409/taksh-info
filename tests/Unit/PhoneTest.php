<?php

namespace Tests\Unit;

use App\Helpers\Otp;
use App\Helpers\Phone;
use PHPUnit\Framework\TestCase;

class PhoneTest extends TestCase
{
    public function test_normalize_accepts_common_formats(): void
    {
        foreach (['9876543210', '+91 98765 43210', '098765-43210', '919876543210'] as $in) {
            $this->assertSame('9876543210', Phone::normalize($in), $in);
        }
        foreach (['', null, '12345', '98765432101234'] as $in) {
            $this->assertNull(Phone::normalize($in));
        }
    }

    public function test_display_and_mask(): void
    {
        $this->assertSame('+91 98765 43210', Phone::display('9876543210'));
        $this->assertSame('', Phone::display('abc'));
        $this->assertSame('98******10', Phone::mask('9876543210'));
    }
}
