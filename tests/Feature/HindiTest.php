<?php

namespace Tests\Feature;

use App\Services\HindiPage;
use Tests\TestCase;

/** Hindi lives in lang/hi.json and the server translates the pages with it. */
class HindiTest extends TestCase
{
    private function hindi(): HindiPage
    {
        app()->setLocale('hi');

        return app(HindiPage::class);
    }

    public function test_the_language_file_is_valid_and_has_the_common_texts(): void
    {
        $dict = json_decode(file_get_contents(base_path('lang/hi.json')), true, 512, JSON_THROW_ON_ERROR);

        $this->assertGreaterThan(150, count($dict));
        foreach (['Home', 'My Cart', 'Wallet', 'Add to Cart', 'Book Now', 'Request a callback', 'Notifications Hub'] as $key) {
            $this->assertArrayHasKey($key, $dict, $key);
            $this->assertNotSame($key, $dict[$key]);
        }
        foreach ($dict as $k => $v) {
            $this->assertIsString($v);
            $this->assertNotSame('', trim($v), $k);
        }
    }

    public function test_only_whole_texts_are_translated_and_data_is_left_alone(): void
    {
        $html = '<h1>Wallet</h1><p> Add to Cart </p><span>Home Wallet</span><a title="Wallet">Fresh Wallet Pouch</a><input placeholder="Search"><b>₹Wallet</b>';

        $out = $this->hindi()->translate($html);

        $this->assertStringContainsString('<h1>वॉलेट</h1>', $out);
        $this->assertStringContainsString('<p> कार्ट में जोड़ें </p>', $out);          // the spaces around it are kept
        $this->assertStringContainsString('<span>Home Wallet</span>', $out);          // part of a longer text: not touched
        $this->assertStringContainsString('Fresh Wallet Pouch', $out);                // a product name that merely contains a word
        $this->assertStringContainsString('title="Wallet"', $out);                    // attributes other than placeholder are left alone
        $this->assertStringContainsString('placeholder="खोजें"', $out);
        $this->assertStringContainsString('<b>₹Wallet</b>', $out);
    }

    public function test_scripts_styles_and_textareas_are_never_changed(): void
    {
        $html = '<p>Wallet</p><script>var a = "<b>Wallet</b>";</script><style>.x::after{content:">Wallet<"}</style><textarea>Wallet</textarea><p>Wallet</p>';

        $out = $this->hindi()->translate($html);

        $this->assertSame(2, substr_count($out, 'वॉलेट'));
        $this->assertStringContainsString('<script>var a = "<b>Wallet</b>";</script>', $out);
        $this->assertStringContainsString('<textarea>Wallet</textarea>', $out);
        $this->assertStringContainsString('>Wallet<', $out);
    }

    public function test_english_is_returned_untouched(): void
    {
        app()->setLocale('en');
        $html = '<h1>Wallet</h1>';

        $this->assertSame($html, app(HindiPage::class)->translate($html));
        $this->assertSame([], app(HindiPage::class)->dictionary());
    }

    public function test_the_language_cookie_switches_the_whole_page(): void
    {
        $this->get('/wallet')->assertOk()->assertSee('<html lang="en"', false)->assertDontSee('TAKSH_I18N', false)->assertSee('Login with OTP');

        $this->withUnencryptedCookie('taksh_lang', 'hi')->get('/wallet')->assertOk()
            ->assertSee('<html lang="hi"', false)->assertSee('window.TAKSH_I18N', false)
            ->assertSee('OTP से लॉगिन करें')->assertDontSee('>Login with OTP<', false);   // the prompt on the page is translated by the server
    }

    public function test_an_unknown_language_is_english_and_json_is_never_translated(): void
    {
        $this->withUnencryptedCookie('taksh_lang', 'fr')->get('/wallet')->assertSee('<html lang="en"', false);

        $this->withUnencryptedCookie('taksh_lang', 'hi')->getJson('/auth/session')->assertOk()->assertJsonPath('message', 'Session fetched successfully');
    }

    public function test_the_browser_gets_the_same_dictionary_for_what_it_draws(): void
    {
        $html = $this->withUnencryptedCookie('taksh_lang', 'hi')->get('/about')->getContent();

        $this->assertStringContainsString('window.TAKSH_I18N', $html);      // the header, footer and page scripts translate what they draw from the same file
        $this->assertStringContainsString('"About Us":"', $html);
    }
}
