<?php

namespace App\Http\Requests\Web\Catalog;

use Illuminate\Http\Request;

/**
 * What the shop page's address bar asks for: ?q= &category= (or category[]= for several) &max= &quick=1 &page=. Anything unusable is
 * ignored (a hand-edited URL shows the plain shop instead of an error page), so a bookmark or a search engine link can never break the page.
 */
final class ListingFilters
{
    public const PER_PAGE = 12;

    /** @param int[] $categories */
    public function __construct(
        public readonly string $q = '',
        public readonly array $categories = [],
        public readonly int $max = 0,
        public readonly bool $quick = false,
    ) {}

    public static function from(Request $request): self
    {
        $q = trim((string) $request->query('q', ''));
        $max = $request->query('max');
        $raw = $request->query('category', []);

        $categories = collect(is_array($raw) ? $raw : [$raw])
            ->filter(fn ($v) => is_scalar($v))
            ->map(fn ($v) => (int) $v)
            ->filter(fn (int $v) => $v > 0)
            ->unique()->take(50)->values()->all();

        return new self(
            mb_substr($q, 0, 255),
            $categories,
            is_numeric($max) && $max > 0 ? (int) $max : 0,
            $request->query('quick') === '1',
        );
    }

    public function withCategories(array $categories): self
    {
        return new self($this->q, $categories, $this->max, $this->quick);
    }

    public function has(int $categoryId): bool
    {
        return in_array($categoryId, $this->categories, true);
    }

    /** The CatalogService::list() filters. */
    public function toCatalog(): array
    {
        return array_filter([
            'search' => $this->q ?: null,
            'category_ids' => $this->categories ?: null,
            'max_price' => $this->max ?: null,
            'express' => $this->quick ?: null,
            'limit' => self::PER_PAGE,
        ], fn ($v) => $v !== null);
    }

    /**
     * The query string of the current filters (for links that keep them), minus $without; $dropCategory removes one category
     * from the selection.
     */
    public function query(string|array $without = [], ?int $dropCategory = null): array
    {
        $categories = array_values(array_diff($this->categories, $dropCategory ? [$dropCategory] : []));
        $query = array_filter([
            'q' => $this->q,
            'category' => count($categories) > 1 ? $categories : ($categories[0] ?? null),
            'max' => $this->max,
            'quick' => $this->quick ? 1 : null,
        ]);

        return array_diff_key($query, array_flip((array) $without));
    }

    public function any(): bool
    {
        return $this->q !== '' || $this->categories !== [] || $this->max > 0;
    }
}
