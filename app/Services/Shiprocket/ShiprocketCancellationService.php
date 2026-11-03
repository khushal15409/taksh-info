<?php

namespace App\Services\Shiprocket;

/**
 * Cancels a Shiprocket order: POST orders/cancel {"ids": [<shiprocket order id>]}.
 * Shiprocket also drops the AWB of an order that has not been picked up. HTTP only, no database.
 */
class ShiprocketCancellationService
{
    public function __construct(private ShiprocketClient $client)
    {
    }

    /**
     * Succeeds when the order is cancelled, including when Shiprocket says it already was.
     *
     * @throws ShiprocketException CLIENT_ERROR = Shiprocket refused (e.g. already picked up);
     *                             anything else = outcome unknown, safe to retry
     */
    public function cancelOrder(string $shiprocketOrderId): void
    {
        try {
            $this->client->post('orders/cancel', ['ids' => [(int) $shiprocketOrderId]]);
        } catch (ShiprocketException $e) {
            if ($e->category === ShiprocketException::CLIENT_ERROR
                && preg_match('/already\s+(been\s+)?cancell?ed|\b(is|was|been)\s+cancell?ed\b/i', (string) $e->providerMessage)) {
                return;
            }

            throw $e;
        }
    }
}
