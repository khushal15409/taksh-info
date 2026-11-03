<?php

namespace App\Http\Requests\Web\Catalog;

use Illuminate\Http\Request;

/**
 * What the shop page's address bar asks for: ?q= &category= &max= &page=. Anything unusable is ignored (a hand-edited URL shows the
 * plain shop instead of an error page), so a bookmark or a search engine link can never break the page.
 */
final class ListingFilters
{
    public const PER_PAGE = 12;

    public function __construct(
        public readonly string $q = '',
        public readonly int $category = 0,
        public readonly int $max = 0,
    ) {}

    public static function from(Request $request): self
    {
        $q = trim((string) $request->query('q', ''));
        $max = $request->query('max');

        return new self(
            mb_substr($q, 0, 255),
            max(0, (int) $request->query('category', 0)),
            is_numeric($max) && $max > 0 ? (int) $max : 0,
        );
    }

    /** The CatalogService::list() filters. */
    public function toCatalog(): array
    {
        return array_filter([
            'search' => $this->q ?: null,
            'category_id' => $this->category ?: null,
            'max_price' => $this->max ?: null,
            'limit' => self::PER_PAGE,
        ], fn ($v) => $v !== null);
    }

    /** The query string of the current filters (for links that keep them), minus $without. */
    public function query(string ...$without): array
    {
        return array_diff_key(array_filter(['q' => $this->q, 'category' => $this->category, 'max' => $this->max]), array_flip($without));
    }

    public function any(): bool
    {
        return $this->q !== '' || $this->category > 0 || $this->max > 0;
    }
}
