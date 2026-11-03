<?php

namespace App\Support\Presenters;

/**
 * Arranges the dashboard sections (GET /dashboard shape) into what the home page draws, in the order the app uses: the top banners
 * as a slider, the category strip, then the admin's sections - with a "home_middle" banner right below the section it names (or after
 * the first half when it names none) and the "home_bottom" banners at the end.
 */
final class HomeLayout
{
    private const FIXED = ['Logo', 'Banners', 'Categories'];

    /** @return array{has_content: bool, hero: list<array>, categories: list<array>, blocks: list<array{type: string, section?: array, banner?: array, kind?: string}>} */
    public static function from(array $sections): array
    {
        $by = fn (string $key) => collect($sections)->firstWhere('key', $key)['data'] ?? [];
        $banners = collect($by('Banners'));
        $categories = $by('Categories');
        $dynamic = collect($sections)->reject(fn ($s) => in_array($s['key'], self::FIXED, true))->values();

        $hasContent = $banners->isNotEmpty() || $categories !== [] || $dynamic->contains(fn ($s) => ! empty($s['data']));

        $middle = $banners->where('position', 'home_middle')->values();
        $used = [];
        $blocks = [];
        $half = (int) ceil($dynamic->count() / 2) - 1;

        foreach ($dynamic as $i => $section) {
            if (! empty($section['data'])) {
                $blocks[] = ['type' => 'section', 'section' => $section, 'kind' => self::kind($section['data'])];
            }
            foreach ($middle->where('after_section_name', $section['key']) as $b) {
                $blocks[] = ['type' => 'banner', 'banner' => $b];
                $used[] = $b['id'];
            }
            if ($i === $half) {
                foreach ($middle->filter(fn ($b) => empty($b['after_section_name']) && ! in_array($b['id'], $used, true)) as $b) {
                    $blocks[] = ['type' => 'banner', 'banner' => $b];
                    $used[] = $b['id'];
                }
            }
        }
        foreach ($banners->where('position', 'home_bottom') as $b) {
            $blocks[] = ['type' => 'banner', 'banner' => $b];
        }

        return [
            'has_content' => $hasContent,
            'hero' => $banners->filter(fn ($b) => in_array($b['position'] ?? null, ['home_top', 'dashboard'], true))->values()->all(),
            'categories' => $categories,
            'blocks' => $blocks,
        ];
    }

    /** products | categories | banners, from the first item. */
    public static function kind(array $items): string
    {
        $first = $items[0] ?? [];

        return array_key_exists('price', $first) ? 'products' : (array_key_exists('slug', $first) || array_key_exists('name', $first) ? 'categories' : 'banners');
    }

    /** Where a banner leads: its product, its sale, or the shop. */
    public static function bannerUrl(array $b): string
    {
        return match (true) {
            ($b['redirect_type'] ?? null) === 'product' && ! empty($b['redirect_id']) => route('product', ['id' => $b['redirect_id']]),
            ($b['redirect_type'] ?? null) === 'sale' && ! empty($b['redirect_id']) => route('sales.detail', $b['redirect_id']),
            default => route('listing'),
        };
    }

    /** The look of a section, by the design the admin chose. */
    public static function design(?string $design): array
    {
        return [
            'premium' => ['box' => 'bg-gray-900 rounded-3xl p-5 lg:p-6 border border-yellow-500/30', 'title' => 'text-yellow-400'],
            'hot_deals' => ['box' => 'bg-gradient-to-r from-red-600 to-orange-500 rounded-3xl p-5 lg:p-6', 'title' => 'text-white'],
            'fresh_picks' => ['box' => 'bg-gradient-to-r from-emerald-700 to-lime-500 rounded-3xl p-5 lg:p-6', 'title' => 'text-white'],
            'spotlight' => ['box' => 'bg-orange-50/70 rounded-3xl p-5 lg:p-6 border border-orange-200/60', 'title' => 'text-gray-900'],
        ][$design] ?? ['box' => '', 'title' => 'text-gray-900'];
    }
}
