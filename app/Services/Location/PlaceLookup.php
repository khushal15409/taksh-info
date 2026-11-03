<?php

namespace App\Services\Location;

interface PlaceLookup
{
    /** Places matching a typed address. @return list<array<string, mixed>> */
    public function search(string $query, int $limit = 6): array;

    /** The place at a point, or null when nothing is known there. */
    public function reverse(float $latitude, float $longitude): ?array;
}
