<?php

namespace App\Services\Shiprocket;

/**
 * Fetches current tracking from Shiprocket for one AWB: GET courier/track/awb/{awb}.
 * Returns normalized events only (same shape CourierTrackingService::ingest takes), never the raw response.
 */
class ShiprocketTrackingService
{
    public function __construct(private ShiprocketClient $client)
    {
    }

    /**
     * @return array{events: array<int, array<string, mixed>>, tracking_url: ?string}
     *
     * @throws ShiprocketException
     */
    public function fetch(string $awb): array
    {
        $body = $this->client->get('courier/track/awb/'.rawurlencode($awb));

        $data = $body['tracking_data'] ?? null;
        if (! is_array($data)) {
            throw new ShiprocketException('Shiprocket tracking response was invalid.', ShiprocketException::INVALID_RESPONSE);
        }
        if (($data['track_status'] ?? 0) != 1) {
            return ['events' => [], 'tracking_url' => null]; // Shiprocket has nothing for this AWB yet
        }

        $events = [];
        foreach ((array) ($data['shipment_track_activities'] ?? []) as $a) {
            $label = is_array($a) ? ($a['sr-status-label'] ?? $a['status'] ?? null) : null;
            if (! is_string($label) || trim($label) === '') {
                continue;
            }
            $events[] = [
                'label' => $label,
                'status_code' => isset($a['sr-status']) ? (string) $a['sr-status'] : null,
                'description' => isset($a['activity']) ? (string) $a['activity'] : null,
                'location' => isset($a['location']) ? (string) $a['location'] : null,
                'time' => ShiprocketStatus::parseTime($a['date'] ?? null),
            ];
        }

        $url = $data['track_url'] ?? null;

        return ['events' => $events, 'tracking_url' => is_string($url) ? $url : null];
    }
}
