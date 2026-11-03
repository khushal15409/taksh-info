<?php

namespace App\Http\Controllers\Web\Wishlist;

use App\Http\Controllers\Web\WebController;
use App\Services\WishlistService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** The customer's saved products. The heart on any card adds or removes (a small call); the page lists what is saved. */
class WishlistPageController extends WebController
{
    private const PER_PAGE = 24;

    public function __construct(private readonly WishlistService $wishlist) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        if (! $user) {
            return $this->page('wishlist.index', ['items' => null]);
        }

        $data = $this->wishlist->list($user->id, self::PER_PAGE);
        $p = $data['pagination'];
        $paginator = (new \Illuminate\Pagination\LengthAwarePaginator($data['items'], $p['total'], $p['per_page'], $p['current_page'], ['path' => route('wishlist')]));

        return $this->page('wishlist.index', ['items' => $data['items'], 'paginator' => $paginator]);
    }

    /** DELETE /wishlist/{productId} - the no-JS way to un-save a product */
    public function destroy(Request $request, int $productId): RedirectResponse
    {
        $this->wishlist->remove($request->user()->id, $productId);

        return $this->redirectWith('wishlist', 'success', 'Removed from wishlist');
    }
}
