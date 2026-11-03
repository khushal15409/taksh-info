<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

/** The few shop categories the footer links to: the first top-level categories of the catalogue, kept for ten minutes. An extra: empty if unreadable. */
class FooterCategories
{
    private const COUNT = 6;

    public function __construct(private readonly CategoryService $categories) {}

    /** @return list<array{id: int, name: string}> */
    public function get(): array
    {
        try {
            return Cache::remember('footer:categories', now()->addMinutes(10), fn () => collect($this->categories->tree('standard_delivery'))
                ->take(self::COUNT)->map(fn ($c) => ['id' => $c['id'], 'name' => $c['name']])->values()->all());
        } catch (\Throwable $e) {
            report($e);

            return [];
        }
    }
}
