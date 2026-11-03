<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Sale;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Repositories\Contracts\SaleRepositoryInterface;
use Illuminate\Support\Collection;

/** Which sale a product falls under and what it costs after the sale (same rules as taksh-admin's SaleService). */
class SaleService
{
    private ?Collection $active = null;

    public function __construct(
        private readonly SaleRepositoryInterface $sales,
        private readonly ProductRepositoryInterface $products,
    ) {}

    /** @return array<string, mixed>|null sale data, or null when no sale applies */
    public function applySale(Product $product, ?int $fcId = null): ?array
    {
        $best = null;

        foreach ($this->activeSales() as $sale) {
            if ($this->appliesTo($sale, $product, $fcId) && (! $best || $sale->priority > $best->priority)) {
                $best = $sale;
            }
        }

        $variant = $best ? $this->products->cheapestActiveVariant($product) : null;

        if (! $best || ! $variant) {
            return null;
        }

        $original = (float) $variant->price;
        $discount = $best->calculateDiscount($original);

        return [
            'sale_id' => $best->id,
            'sale_name' => $best->name,
            'sale_type' => $best->sale_type,
            'original_price' => $original,
            'sale_price' => round(max(0, $original - $discount), 2),
            'discount_amount' => round($discount, 2),
            'discount_label' => $best->getDiscountLabel(),
            'discount_type' => $best->discount_type,
            'discount_value' => $best->discount_value,
        ];
    }

    /** Active sales; with a center, only the ones that can apply there. */
    public function getActiveSales(?int $fcId = null): Collection
    {
        $sales = $this->activeSales();

        if (! $fcId) {
            return $sales;
        }

        return $sales->filter(function (Sale $sale) use ($fcId) {
            if (in_array($sale->sale_type, ['global', 'category', 'product'], true)) {
                return true;
            }

            if (in_array($sale->sale_type, ['fc', 'express_30'], true)) {
                $ids = $this->sales->fulfillmentCenterIds($sale);

                return $ids === [] || in_array($fcId, $ids, true);
            }

            return false;
        })->values();
    }

    private function activeSales(): Collection
    {
        return $this->active ??= $this->sales->active();
    }

    private function appliesTo(Sale $sale, Product $product, ?int $fcId): bool
    {
        switch ($sale->sale_type) {
            case 'global':
                return true;

            case 'category':
                return in_array($product->category_id, $this->sales->categoryIds($sale));

            case 'product':
                return $this->sales->includesProduct($sale, $product->id);

            case 'fc':
                return $fcId
                    && in_array($fcId, $this->sales->fulfillmentCenterIds($sale))
                    && $product->fulfillment_center_id == $fcId;

            case 'express_30':
                if (! $product->is_express_30) {
                    return false;
                }

                if ($fcId) {
                    if (! $this->sales->fulfillmentCenterSupportsExpress($fcId)) {
                        return false;
                    }

                    $ids = $this->sales->fulfillmentCenterIds($sale);

                    if ($ids !== [] && ! in_array($fcId, $ids)) {
                        return false;
                    }
                }

                return true;

            default:
                return false;
        }
    }
}
