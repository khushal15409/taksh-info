<?php

namespace Tests\Feature;

use Tests\CatalogTestCase;

class SecurityHeadersTest extends CatalogTestCase
{
    public function test_pages_carry_the_security_headers_and_a_csp_without_unsafe_inline_scripts(): void
    {
        $r = $this->get('/about')->assertOk();

        $csp = $r->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertStringContainsString("frame-ancestors 'self'", $csp);
        $this->assertStringContainsString('https://checkout.razorpay.com', $csp);          // the payment window must keep working
        $this->assertMatchesRegularExpression("/script-src [^;]*'nonce-[A-Za-z0-9+\\/=]+'/", $csp);
        $this->assertDoesNotMatchRegularExpression("/script-src [^;]*'unsafe-inline'/", $csp);

        $r->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_every_inline_script_carries_the_nonce_and_no_inline_handlers_are_left(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertDoesNotMatchRegularExpression('/<script(?![^>]*\bsrc=)(?![^>]*nonce=)[^>]*>/i', $html);
        $this->assertDoesNotMatchRegularExpression('/\son(click|submit|change|load|error)=["\']/i', $html);
    }

    public function test_the_page_shell_does_not_hand_the_user_id_or_email_to_every_page(): void
    {
        $html = $this->actingAs($this->customer(7))->get('/')->getContent();

        preg_match('/data-user="([^"]*)"/', $html, $m);
        $user = json_decode(html_entity_decode($m[1] ?? ''), true) ?: [];

        $this->assertArrayNotHasKey('id', $user);
        $this->assertArrayNotHasKey('email', $user);
    }
}
