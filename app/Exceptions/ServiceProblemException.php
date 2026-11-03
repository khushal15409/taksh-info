<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;

/** A refused service / courier action: a message (key or text), an HTTP status and optional typed data for the client. */
class ServiceProblemException extends ApiException
{
    public function __construct(string $message, int $status = 422, private readonly ?array $data = null)
    {
        parent::__construct($message);
        $this->status = $status;
    }

    public function status(): int
    {
        return $this->status;
    }

    public function render(): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $this->resolveMessage($this->getMessage()), 'data' => $this->data], $this->status);
    }

    /** The Shiprocket quote the customer saw is no longer valid: the client must refresh it. */
    public static function quoteChanged(string $message, int $status = 409): self
    {
        return new self($message, $status, ['code' => 'quote_changed']);
    }
}
