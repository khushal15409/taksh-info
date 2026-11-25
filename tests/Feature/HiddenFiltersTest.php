<?php

namespace Tests\Feature;

use App\Support\FilterToken;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/** Search / filter terms stay out of the address bar: the `hidefilters` middleware and the URL generator, on throw-away routes. */
class HiddenFiltersTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['taksh.hide_filters.enabled' => true]);
        config(['taksh.hide_filters.scopes._t' => ['q', 'category', 'max']]);

        Route::middleware('web')->group(function () {
            Route::get('/_f/list', fn (\Illuminate\Http\Request $r) => response()->json(['q' => $r->query('q'), 'category' => $r->query('category'), 'max' => $r->query('max'), 'quick' => $r->query('quick'), 'raw' => $r->getQueryString()]))
                ->middleware('hidefilters:_t')->name('_f.list');
            Route::post('/_f/list', \App\Http\Controllers\Web\FilterRedirectController::class)->middleware('hidefilters:_t')->name('_f.search');
        });
        app('router')->getRoutes()->refreshNameLookups();
    }

    public function test_a_token_round_trips_and_is_bound_to_its_page(): void
    {
        $terms = FilterToken::clean('_t', ['q' => ' samsung tv ', 'category' => ['3', '5', ''], 'max' => '500', 'junk' => 'x']);
        $token = FilterToken::encode('_t', $terms);

        $this->assertSame(['category' => ['3', '5'], 'max' => '500', 'q' => 'samsung tv'], FilterToken::decode('_t', $token));
        $this->assertNull(FilterToken::decode('listing', $token));          // another page's token is nothing
        $this->assertNull(FilterToken::decode('_t', substr($token, 0, -3).'AAA'));   // edited
        $this->assertNull(FilterToken::decode('_t', 'not a token'));
        $this->assertStringNotContainsString('samsung', $token);
        $this->assertSame($token, FilterToken::encode('_t', $terms));        // stable: the same search is the same URL
    }

    public function test_a_posted_search_redirects_to_a_url_without_the_words(): void
    {
        $res = $this->withSession(['_token' => 'tok'])->post('/_f/list', ['_token' => 'tok', 'q' => 'secret words', 'category' => ['3'], 'quick' => '1', 'other' => 'dropped']);

        $res->assertStatus(303);
        $location = $res->headers->get('Location');
        $this->assertStringNotContainsString('secret', urldecode($location));
        $this->assertStringContainsString('quick=1', $location);
        $this->assertStringContainsString('f=', $location);
        $this->assertStringNotContainsString('other', $location);

        $this->get($location)->assertOk()->assertJsonPath('q', 'secret words')->assertJsonPath('category.0', '3')->assertJsonPath('quick', '1');
    }

    public function test_an_old_link_with_the_words_in_it_is_redirected_to_the_token_url(): void
    {
        $res = $this->get('/_f/list?q=tea&max=200&quick=1&page=2');

        $res->assertStatus(302);
        $location = $res->headers->get('Location');
        $this->assertStringNotContainsString('tea', urldecode($location));
        $this->assertStringContainsString('page=2', $location);
        $this->get($location)->assertOk()->assertJsonPath('q', 'tea')->assertJsonPath('max', '200');
    }

    public function test_route_helper_writes_the_token_not_the_words(): void
    {
        $url = route('_f.list', ['q' => 'tea', 'category' => [3], 'quick' => 1]);

        $this->assertStringNotContainsString('tea', $url);
        $this->assertStringNotContainsString('category', $url);
        $this->assertStringContainsString('quick=1', $url);
        $this->get($url)->assertOk()->assertJsonPath('q', 'tea')->assertJsonPath('category.0', '3');
        $this->assertSame(route('_f.list'), route('_f.list', ['q' => '']));   // nothing to hide, nothing added
    }

    public function test_a_broken_token_just_shows_the_unfiltered_page(): void
    {
        $this->get('/_f/list?f=garbage')->assertOk()->assertJsonPath('q', null);
    }

    public function test_page_links_carry_the_token(): void
    {
        $request = \Illuminate\Http\Request::create('/_f/list', 'GET', ['q' => 'tea', 'max' => '9']);

        $query = FilterToken::pageQuery('_t', $request, ['quick' => 1]);

        $this->assertSame(['quick', 'f'], array_keys($query));
        $this->assertSame(['max' => '9', 'q' => 'tea'], FilterToken::decode('_t', $query['f']));
    }

    public function test_turning_it_off_gives_plain_terms_again(): void
    {
        config(['taksh.hide_filters.enabled' => false]);

        $this->assertStringContainsString('q=tea', route('_f.list', ['q' => 'tea']));
        $this->get('/_f/list?q=tea')->assertOk()->assertJsonPath('q', 'tea');
        $this->assertSame(['q' => 'tea'], FilterToken::pageQuery('_t', \Illuminate\Http\Request::create('/x', 'GET', ['q' => 'tea', 'z' => 1])));
    }
}
