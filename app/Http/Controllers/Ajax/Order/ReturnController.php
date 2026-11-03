<?php

namespace App\Http\Controllers\Ajax\Order;

use App\Http\Controllers\Controller;
use App\Http\Requests\Order\CreateReturnRequest;
use App\Http\Requests\Order\ReturnListRequest;
use App\Http\Requests\Order\UploadReturnMediaRequest;
use App\Services\Returns\ReturnService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReturnController extends Controller
{
    use ApiResponseTrait;

    public function __construct(private readonly ReturnService $returns) {}

    /** POST /return/customer/create */
    public function create(CreateReturnRequest $request): JsonResponse
    {
        return $this->success($this->returns->create($request->user(), $request->validated()), 'api.return_requested');
    }

    /** POST /return/customer/upload-media (multipart: return_id, images[]) */
    public function uploadMedia(UploadReturnMediaRequest $request): JsonResponse
    {
        return $this->success($this->returns->uploadMedia($request->user(), (int) $request->validated('return_id'), $request->file('images')), 'api.media_uploaded');
    }

    /** GET /return/customer/list?status= */
    public function index(ReturnListRequest $request): JsonResponse
    {
        return $this->success($this->returns->list($request->user(), $request->validated('status')), 'api.returns_retrieved');
    }

    /** GET /return/customer/{id} */
    public function show(Request $request, int $id): JsonResponse
    {
        return $this->success($this->returns->show($request->user(), $id), 'api.return_retrieved');
    }

    /** GET /return/customer/{id}/track */
    public function track(Request $request, int $id): JsonResponse
    {
        return $this->success($this->returns->track($request->user(), $id), 'api.status_tracked');
    }
}
