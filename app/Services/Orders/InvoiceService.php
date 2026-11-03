<?php

namespace App\Services\Orders;

use App\Models\User;
use App\Repositories\Contracts\OrderRepositoryInterface;
use Illuminate\Support\Facades\Http;

/**
 * The invoice PDF is rendered by taksh-admin (its template, GST/TDS rules and barcodes live there). The website checks that the
 * order is the customer's own and then fetches that PDF from taksh-admin on the customer's behalf, so the admin URL is never
 * handed out and nobody can pull another customer's invoice through the website.
 */
class InvoiceService
{
    public function __construct(private readonly OrderRepositoryInterface $orders) {}

    /** Does this order number belong to the customer? */
    public function owns(User $user, string $orderNumber): bool
    {
        return $this->orders->numberOwnedBy($user->id, trim($orderNumber));
    }

    /** @return array{body: string, filename: string}|null null when taksh-admin could not produce it */
    public function pdf(string $orderNumber, string $part = 'both'): ?array
    {
        $response = Http::timeout(30)->accept('application/pdf')
            ->get(rtrim((string) config('taksh.admin_url'), '/').'/api/orders/'.rawurlencode(trim($orderNumber)).'/invoice', ['part' => $part]);

        if ($response->failed() || ! str_contains((string) $response->header('Content-Type'), 'pdf')) {
            return null;
        }

        return ['body' => $response->body(), 'filename' => 'invoice-'.preg_replace('/[^A-Za-z0-9_-]/', '', $orderNumber).'.pdf'];
    }
}
