<?php

namespace App\Helpers;

use App\Models\Category;
use App\Models\CustomCharge;
use App\Models\CustomChargeOverride;
use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Admin-defined, labelled extra charges shown on the customer's bill (read-only copy of taksh-admin's helper: same rules, so the site and the app bill the same) (any number of them).
 *
 * Each charge (Settings > App Charges) has its own label, on/off, which orders it applies to
 * (standard, quick or both) and a rule: fixed rupees, a percent of the unit price, or by price
 * range. A product may carry its own amount for a charge and so may a category. Per unit the
 * amount is: the product's, else its category's (then the parent category's), else the rule.
 * Every charge makes ONE bill line; no amount means no line at all.
 */
class ExtraCharge
{
    public const TYPE = 'extra';

    private static ?Collection $cache = null;

    private static array $overrideCache = [];

    public static function flush(): void
    {
        self::$cache = null;
        self::$overrideCache = [];
    }

    /** All charges, in the order admin set. */
    public static function all(): Collection
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        // an older database without the admin's custom-charge tables simply has no extra charges
        try {
            return self::$cache = Schema::hasTable('custom_charges') && Schema::hasTable('custom_charge_overrides')
                ? CustomCharge::orderBy('sort_order')->orderBy('id')->get()
                : collect();
        } catch (\Throwable $e) {
            report($e);

            return self::$cache = collect();
        }
    }

    /** Charges that are switched on (what product / category forms offer). */
    public static function enabled(): Collection
    {
        return self::all()->where('enabled', true)->values();
    }

    public static function anyOn(): bool
    {
        return self::enabled()->isNotEmpty();
    }

    public static function activeFor(CustomCharge $charge, bool $isQuick): bool
    {
        if (! $charge->enabled) {
            return false;
        }

        return $charge->applies_to === 'both' || ($isQuick ? $charge->applies_to === 'quick' : $charge->applies_to === 'standard');
    }

    /** Worked out for one unit: rupees as is, a percent of the unit price. */
    private static function compute(string $type, float $value, float $unitPrice): float
    {
        return max(0.0, round($type === 'percent' ? $unitPrice * $value / 100 : $value, 2));
    }

    private static function override(CustomCharge $charge, string $scope, int $id): ?CustomChargeOverride
    {
        $key = $charge->id.':'.$scope.':'.$id;
        if (! array_key_exists($key, self::$overrideCache)) {
            self::$overrideCache[$key] = CustomChargeOverride::where('custom_charge_id', $charge->id)
                ->where('scope', $scope)->where('scope_id', $id)->first();
        }

        return self::$overrideCache[$key];
    }

    /** Is this product one the charge is meant for? (all products, or only the picked categories and their sub categories) */
    public static function inScope(CustomCharge $charge, ?Product $product, float $unitPrice = 0.0): bool
    {
        // 'categories' and the older 'both' mean category-limited; anything else (all / old 'price') means every product.
        if (! in_array($charge->scope_mode, ['categories', 'both'], true)) {
            return true;
        }

        $ids = array_map('intval', (array) $charge->scope_category_ids);
        if (! $ids) {
            return true; // categories mode but none picked: do not silently switch the charge off
        }

        $id = $product?->category_id;
        for ($depth = 0; $id && $depth < 4; $depth++) {
            if (in_array((int) $id, $ids, true)) {
                return true;
            }
            $id = Category::whereKey($id)->value('parent_id');
        }

        return false;
    }

    /** Per-unit amount of one charge for one product at this unit price. */
    public static function unitAmount(CustomCharge $charge, ?Product $product, float $unitPrice = 0.0): float
    {
        if (! self::inScope($charge, $product, $unitPrice)) {
            return 0.0;
        }

        if ($product) {
            if ($o = self::override($charge, 'product', (int) $product->id)) {
                return self::compute($o->type ?: 'fixed', (float) $o->amount, $unitPrice);
            }

            $id = $product->category_id;
            for ($depth = 0; $id && $depth < 4; $depth++) {
                if ($o = self::override($charge, 'category', (int) $id)) {
                    return self::compute($o->type ?: 'fixed', (float) $o->amount, $unitPrice);
                }
                $id = Category::whereKey($id)->value('parent_id');
            }
        }

        switch ($charge->type) {
            case 'percent':
                return self::compute('percent', (float) $charge->default_amount, $unitPrice);
            case 'range':
                foreach ((array) $charge->ranges as $r) {
                    $to = $r['to'] ?? null;
                    if ($unitPrice >= (float) $r['from'] && ($to === null || $unitPrice <= (float) $to)) {
                        return self::compute((string) ($r['type'] ?? 'fixed'), (float) $r['value'], $unitPrice);
                    }
                }

                return 0.0;
            default:
                return self::compute('fixed', (float) $charge->default_amount, $unitPrice);
        }
    }

    /**
     * The bill lines (one per active charge that comes to more than 0) for [Product|null, qty, unitPrice] lines.
     *
     * @param  iterable<array{0: ?Product, 1: int, 2?: float}>  $lines
     * @return array<int, array{type: string, label: string, amount: float, is_discount: bool}>
     */
    public static function linesFor(iterable $lines, bool $isQuick): array
    {
        $lines = is_array($lines) ? $lines : iterator_to_array($lines);
        $out = [];

        foreach (self::all() as $charge) {
            if (! self::activeFor($charge, $isQuick)) {
                continue;
            }

            $total = 0.0;
            foreach ($lines as $line) {
                $total += self::unitAmount($charge, $line[0] ?? null, (float) ($line[2] ?? 0)) * max(0, (int) ($line[1] ?? 0));
            }
            $total = round($total, 2);

            if ($total > 0) {
                $out[] = ['type' => self::TYPE, 'label' => $charge->label, 'amount' => $total, 'is_discount' => false];
            }
        }

        return $out;
    }
}
