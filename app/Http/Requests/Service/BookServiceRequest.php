<?php

namespace App\Http\Requests\Service;

use App\Http\Requests\ApiFormRequest;

/**
 * One request books a service: the customer sends the service and the address where it is needed (a saved `address_id` or the
 * details). There is deliberately no user_id / amount / provider field: those are never taken from the client.
 */
class BookServiceRequest extends ApiFormRequest
{
    protected int $failureStatus = 400;

    public function rules(): array
    {
        return [
            'service_id' => ['required', 'integer'],
            'address_id' => ['nullable', 'integer'],
            'name' => ['required_without:address_id', 'nullable', 'string', 'max:255'],
            'mobile' => ['required_without:address_id', 'nullable', 'digits:10'],
            'address_line_1' => ['required_without:address_id', 'nullable', 'string', 'max:500'],
            'address_line_2' => ['nullable', 'string', 'max:500'],
            'landmark' => ['nullable', 'string', 'max:255'],
            'pincode' => ['required_without:address_id', 'nullable', 'digits:6'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'type' => ['nullable', 'in:home,office,other'],
            'customer_notes' => ['nullable', 'string', 'max:500'],
            'scheduled_for' => ['nullable', 'date', 'after:now'],
            'idempotency_key' => ['nullable', 'string', 'max:100'],
        ];
    }

    /** The address details sent with the booking (empty ones dropped). */
    public function addressDetails(): array
    {
        return collect($this->validated())->only(['name', 'mobile', 'address_line_1', 'address_line_2', 'landmark', 'pincode', 'city', 'state', 'latitude', 'longitude', 'type'])
            ->filter(fn ($v) => $v !== null && $v !== '')->all();
    }

    public function idempotencyKey(): ?string
    {
        $key = $this->validated('idempotency_key') ?? $this->header('Idempotency-Key');

        return $key ? substr((string) $key, 0, 100) : null;
    }
}
