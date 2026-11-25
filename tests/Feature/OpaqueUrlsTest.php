<?php

namespace Tests\Feature;

use App\Support\UrlKey;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/** The `urlkey:` middleware and the URL generator, on throw-away routes (no database needed). */
class OpaqueUrlsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['taksh.opaque_urls.enabled' => true]);

        Route::middleware('web')->group(function () {
            Route::get('/_t/shops/{id}', fn (int $id) => response('shop:'.$id))->where('id', '([a-z0-9-]+-)?[0-9a-z]{11}|[0-9]+')->middleware('urlkey:shop')->name('_t.shop');
            Route::get('/_t/tracking', fn (\Illuminate\Http\Request $r) => response('order:'.$r->query('id')))->middleware('urlkey:order,id')->name('_t.tracking');
        });
        app('router')->getRoutes()->refreshNameLookups();
    }

    public function test_route_helper_writes_a_key_not_the_number(): void
    {
        $url = route('_t.shop', ['id' => 12]);

        $this->assertStringNotContainsString('/shops/12', $url);
        $this->assertStringEndsWith(UrlKey::encode('shop', 12), $url);
        $this->assertStringEndsWith(UrlKey::encode('shop', 12), route('_t.shop', 12));
    }

    public function test_a_key_url_reaches_the_controller_with_the_number(): void
    {
        $this->get(route('_t.shop', ['id' => 12]))->assertOk()->assertSee('shop:12');
        $this->get('/_t/shops/anything-'.UrlKey::encode('shop', 12))->assertOk()->assertSee('shop:12');
    }

    public function test_an_old_numeric_link_is_redirected_to_the_key_url(): void
    {
        $this->get('/_t/shops/12?x=1')->assertStatus(301)->assertRedirect(route('_t.shop', ['id' => 12, 'x' => 1]));
    }

    public function test_a_wrong_key_is_a_404(): void
    {
        $this->get('/_t/shops/'.UrlKey::encode('order', 12))->assertNotFound();
        $this->get('/_t/shops/zzzzzzzzzzz')->assertNotFound();
    }

    public function test_turning_legacy_off_makes_numeric_links_a_404(): void
    {
        config(['taksh.opaque_urls.legacy_numeric' => false]);

        $this->get('/_t/shops/12')->assertNotFound();
        $this->get(route('_t.shop', ['id' => 12]))->assertOk();
    }

    public function test_turning_the_feature_off_restores_plain_numbers(): void
    {
        config(['taksh.opaque_urls.enabled' => false]);

        $this->assertStringEndsWith('/_t/shops/12', route('_t.shop', ['id' => 12]));
        $this->get('/_t/shops/12')->assertOk()->assertSee('shop:12');
    }

    public function test_a_query_value_is_decoded_and_an_order_number_is_left_alone(): void
    {
        $this->get('/_t/tracking?id='.UrlKey::encode('order', 44))->assertOk()->assertSee('order:44');
        $this->get('/_t/tracking?id=TK-ABC123')->assertOk()->assertSee('order:TK-ABC123');
        $this->get('/_t/tracking?id=44')->assertStatus(301);
        $this->assertStringContainsString('id='.UrlKey::encode('order', 44), route('_t.tracking', ['id' => 44]));
    }
}
