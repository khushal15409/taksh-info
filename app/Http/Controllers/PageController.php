<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

class PageController extends Controller
{
    public function home(): View
    {
        return view('pages.home');
    }

    public function listing(): View
    {
        return view('pages.listing');
    }

    public function product(): View
    {
        return view('pages.product-details');
    }

    public function cart(): View
    {
        return view('pages.cart');
    }

    public function checkout(): View
    {
        return view('pages.checkout');
    }

    public function tracking(): View
    {
        return view('pages.tracking');
    }

    public function profile(): View
    {
        return view('pages.profile');
    }

    public function notifications(): View
    {
        return view('pages.notifications');
    }

    public function services(): View
    {
        return view('pages.services');
    }

    public function wishlist(): View
    {
        return view('pages.wishlist');
    }

    public function privacy(): View
    {
        return view('pages.privacy');
    }

    public function terms(): View
    {
        return view('pages.terms');
    }
}
