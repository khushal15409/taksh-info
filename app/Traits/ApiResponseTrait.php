<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Lang;

/**
 * The same {success, message, data} envelope the Taksh API returns, so the
 * website and the mobile app speak one response format.
 */
trait ApiResponseTrait
{
    /** Translate a registered key, otherwise send the text as it is. */
    protected function resolveMessage(string $message): string
    {
        return Lang::has($message) ? __($message) : $message;
    }

    protected function success($data = [], ?string $message = null, int $code = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message ? $this->resolveMessage($message) : __('api.success'),
            'data' => $data,
        ], $code);
    }

    protected function error(string $message, int $code = 500): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $this->resolveMessage($message),
            'data' => null,
        ], $code);
    }

    protected function validationError(array $errors, ?string $message = null, int $code = 400): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message ? $this->resolveMessage($message) : __('api.validation_error'),
            'data' => ['errors' => $errors],
        ], $code);
    }
}
