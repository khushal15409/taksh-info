<?php

namespace App\Http\Controllers\Ajax\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Http\Resources\ProfileResource;
use App\Http\Resources\UserResource;
use App\Services\ProfileService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    use ApiResponseTrait;

    public function __construct(private readonly ProfileService $profile) {}

    /** GET /auth/profile */
    public function show(Request $request): JsonResponse
    {
        return $this->success(ProfileResource::make($request->user())->resolve(), 'api.profile_fetched');
    }

    /** POST /auth/update-profile */
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $this->profile->update($request->user(), $request->validated());

        return $this->success([
            ...ProfileResource::make($user)->resolve(),
            'user' => UserResource::make($user)->resolve(),
        ], 'api.profile_updated');
    }
}
