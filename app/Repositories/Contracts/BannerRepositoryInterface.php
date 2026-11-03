<?php

namespace App\Repositories\Contracts;

use Illuminate\Support\Collection;

interface BannerRepositoryInterface
{
    /**
     * Active banners inside their date window, in admin order.
     *
     * @param  array{position?: ?string, type?: ?string, fc_id?: ?int}  $filters  `type` is ignored unless standard|quick|service;
     *                                                                              `fc_id` keeps banners for that center plus global ones.
     */
    public function active(array $filters = []): Collection;

    /** Active standard banners with the section they sit below (home dashboard). */
    public function standardForDashboard(): Collection;
}
