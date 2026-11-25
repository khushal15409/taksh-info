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

    /**
     * The secret that proves the call comes from the website. It lives in the business_settings table the website and taksh-admin
     * share (created on first use), so it never depends on two .env files holding the same value. Falls back to TAKSH_SITE_API_KEY.
     */
    private function siteKey(): string
    {
        try {
            $row = \Illuminate\Support\Facades\DB::table('business_settings')->where('key', 'site_api_key')->first();
            if ($row && (string) $row->value !== '') {
                return (string) $row->value;
            }

            $key = bin2hex(random_bytes(32));
            \Illuminate\Support\Facades\DB::table('business_settings')->updateOrInsert(['key' => 'site_api_key'], ['value' => $key, 'created_at' => now(), 'updated_at' => now()]);

            return (string) \Illuminate\Support\Facades\DB::table('business_settings')->where('key', 'site_api_key')->value('value');
        } catch (\Throwable $e) {
            report($e);

            return (string) config('taksh.site_api_key');
        }
    }

    /**
     * @return array{body: string, filename: string}
     *
     * @throws \RuntimeException message = a short reason code the customer can quote: admin-unreachable, admin-401 (the shared key is
     *                           not the same on both servers), admin-404, admin-5xx (taksh-admin could not draw the PDF), admin-not-pdf
     */
    public function pdf(int $userId, string $orderNumber, string $part = 'both'): array
    {
        // taksh-admin only serves an invoice to the signed-in customer. The website has already checked who this is, so it
        // identifies itself with the shared secret and names the customer; taksh-admin checks the order is theirs again.
        try {
            $response = Http::timeout(60)->connectTimeout(10)->accept('application/pdf')
                ->withHeaders(['X-Site-Key' => $this->siteKey()])
                ->get(rtrim((string) config('taksh.admin_url'), '/').'/api/site/orders/'.rawurlencode(trim($orderNumber)).'/invoice', ['user_id' => $userId, 'part' => $part]);
        } catch (\Throwable $e) {
            report($e);

            throw new \RuntimeException('admin-unreachable');
        }

        if ($response->failed() || ! str_contains((string) $response->header('Content-Type'), 'pdf')) {
            $status = $response->status();
            \Illuminate\Support\Facades\Log::warning('Invoice PDF not available from taksh-admin', [
                'status' => $status,
                'hint' => in_array($status, [401, 403], true)
                    ? 'taksh-admin refused the X-Site-Key: TAKSH_SITE_API_KEY must be the SAME value in the website .env and the taksh-admin .env (then php artisan config:clear on both)'
                    : ($status === 404 ? 'order not found for this customer, or taksh-admin has no /api/site/orders route yet (deploy it)' : 'see the taksh-admin log'),
                'key_configured_on_website' => (string) config('taksh.site_api_key') !== '',
                'admin_url' => config('taksh.admin_url'),
                'body' => mb_substr((string) $response->body(), 0, 200),
            ]);

            throw new \RuntimeException($response->successful() ? 'admin-not-pdf' : 'admin-'.($status >= 500 ? '5xx' : $status));
        }

        return ['body' => $response->body(), 'filename' => 'invoice-'.preg_replace('/[^A-Za-z0-9_-]/', '', $orderNumber).'.pdf'];
    }
}
