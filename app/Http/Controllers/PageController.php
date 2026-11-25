<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

class PageController extends Controller
{
    public function privacy(): View
    {
        return view('pages.privacy');
    }

    public function terms(): View
    {
        return view('pages.terms');
    }

    public function about(): View
    {
        return view('pages.about');
    }

    public function refund(): View
    {
        return view('pages.refund');
    }

    public function shipping(): View
    {
        return view('pages.shipping');
    }

    public function contact(): View
    {
        return view('pages.contact');
    }

    public function deleteAccount(): View
    {
        return view('pages.delete-account');
    }
}
