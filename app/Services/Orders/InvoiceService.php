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
    public function pdf(int $userId, string $orderNumber, string $part = 'both'): ?array
    {
        // taksh-admin only serves an invoice to the signed-in customer. The website has already checked who this is, so it
        // identifies itself with the shared secret and names the customer; taksh-admin checks the order is theirs again.
        try {
            $response = Http::timeout(30)->accept('application/pdf')
                ->withHeaders(['X-Site-Key' => (string) config('taksh.site_api_key')])
                ->get(rtrim((string) config('taksh.admin_url'), '/').'/api/site/orders/'.rawurlencode(trim($orderNumber)).'/invoice', ['user_id' => $userId, 'part' => $part]);
        } catch (\Throwable $e) {
            report($e);

            return null;
        }

        if ($response->failed() || ! str_contains((string) $response->header('Content-Type'), 'pdf')) {
            \Illuminate\Support\Facades\Log::warning('Invoice PDF not available from taksh-admin', ['status' => $response->status()]);

            return null;
        }

        return ['body' => $response->body(), 'filename' => 'invoice-'.preg_replace('/[^A-Za-z0-9_-]/', '', $orderNumber).'.pdf'];
    }
}
