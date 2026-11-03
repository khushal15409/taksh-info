<?php

namespace Tests\Feature;

use App\Http\Controllers\Web\WebController;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Blade;
use Tests\OrderTestCase;

/** The shared blade components every module page is built from. */
class WebComponentsTest extends OrderTestCase
{
    private function render(string $template, array $data = []): string
    {
        return Blade::render($template, $data);
    }

    public function test_the_layout_component_wraps_content_in_the_site_shell(): void
    {
        $html = $this->render('<x-layout title="Orders | Taksh" page="orders"><p>Hello module</p></x-layout>');

        $this->assertStringContainsString('<title>Orders | Taksh</title>', $html);
        $this->assertStringContainsString('data-page="orders"', $html);
        $this->assertStringContainsString('<p>Hello module</p>', $html);
        $this->assertStringContainsString('assets/js/modules/components.js', $html);
    }

    public function test_empty_state(): void
    {
        $html = $this->render('<x-empty-state emoji="🛒" title="Your cart is empty" text="Add something." cta-label="Shop now" cta-href="/shop" />');

        $this->assertStringContainsString('Your cart is empty', $html);
        $this->assertStringContainsString('Add something.', $html);
        $this->assertStringContainsString('href="/shop"', $html);
        $this->assertStringNotContainsString('<a ', $this->render('<x-empty-state title="Nothing here" />'));
    }

    public function test_status_badge_tones_and_labels(): void
    {
        $this->assertStringContainsString('bg-emerald-100', $this->render('<x-status-badge status="delivered" />'));
        $this->assertStringContainsString('bg-amber-100', $this->render('<x-status-badge status="pending" />'));
        $this->assertStringContainsString('bg-red-100', $this->render('<x-status-badge status="cancelled" />'));
        $this->assertStringContainsString('bg-purple-100', $this->render('<x-status-badge status="in_progress" />'));
        $this->assertStringContainsString('bg-gray-100', $this->render('<x-status-badge status="something_new" />'));
        $this->assertStringContainsString('Provider Assigned', $this->render('<x-status-badge status="provider_assigned" />'));
        $this->assertStringContainsString('Paid online', $this->render('<x-status-badge status="paid" label="Paid online" />'));
    }

    public function test_pagination_shows_pages_and_hides_for_a_single_page(): void
    {
        $many = new LengthAwarePaginator(range(1, 10), 100, 10, 5, ['path' => '/orders']);
        $html = $this->render('<x-pagination :paginator="$p" />', ['p' => $many]);

        $this->assertStringContainsString('aria-current="page"', $html);
        $this->assertStringContainsString('/orders?page=4', $html);   // previous
        $this->assertStringContainsString('/orders?page=6', $html);   // next
        $this->assertStringContainsString('/orders?page=10', $html);  // last

        $one = new LengthAwarePaginator([1, 2], 2, 10, 1, ['path' => '/orders']);
        $this->assertSame('', trim($this->render('<x-pagination :paginator="$p" />', ['p' => $one])));
    }

    public function test_modal_markup(): void
    {
        $html = $this->render('<x-modal id="cancel-order" title="Cancel this order?"><form>Body</form></x-modal>');

        $this->assertStringContainsString('id="cancel-order"', $html);
        $this->assertStringContainsString('hidden', $html);
        $this->assertStringContainsString('Cancel this order?', $html);
        $this->assertStringContainsString('<form>Body</form>', $html);
        $this->assertStringContainsString('data-modal-close', $html);
    }

    public function test_a_flashed_message_becomes_a_toast_on_the_next_page_and_only_once(): void
    {
        \Illuminate\Support\Facades\Route::middleware('web')->get('/_t/flash', fn () => redirect('/about')->with(WebController::FLASH_KEY, ['type' => 'success', 'message' => 'Address saved']));

        $this->get('/_t/flash')->assertRedirect('/about');
        $this->get('/about')->assertOk()->assertSee('toast("Address saved"', false);
        $this->get('/about')->assertOk()->assertDontSee('Address saved', false);   // shown once
    }

    public function test_an_error_toast_is_marked_as_an_error(): void
    {
        $this->withSession([WebController::FLASH_KEY => ['type' => 'error', 'message' => 'Could not cancel']])->get('/about')
            ->assertSee('toast("Could not cancel", "error")', false);
    }

    public function test_the_web_controller_flashes_and_redirects(): void
    {
        $c = new class extends WebController
        {
            public function go()
            {
                return $this->redirectWith('home', 'success', 'Done');
            }
        };

        $res = $c->go();

        $this->assertSame(url('/'), $res->getTargetUrl());
        $this->assertSame(['type' => 'success', 'message' => 'Done'], session(WebController::FLASH_KEY));
    }
}
