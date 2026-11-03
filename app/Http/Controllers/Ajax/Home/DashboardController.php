<?php

namespace App\Http\Controllers\Ajax\Home;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    use ApiResponseTrait;

    public function __construct(private readonly DashboardService $dashboard) {}

    /** GET /dashboard */
    public function index(): JsonResponse
    {
        return $this->success(['sections' => $this->dashboard->standard()], 'api.dashboard.loaded');
    }
}
