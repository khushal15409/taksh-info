<?php

namespace App\Http\Controllers\Web\Catalog;

use App\Http\Controllers\Web\WebController;
use App\Services\SaleCatalogService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;

/** Sales & offers: the running sales, and one sale with the products it covers. */
class SalePageController extends WebController
{
    public function __construct(private readonly SaleCatalogService $sales) {}

    public function index(): View
    {
        return $this->page('catalog.sales', ['sales' => $this->sales->list(null)]);
    }

    public function show(int $id): View|Response
    {
        $sale = $this->sales->find($id);
        if (! $sale) {
            return $this->notFound('catalog.sale', ['sale' => null]);
        }

        return $this->page('catalog.sale', [
            'sale' => $this->sales->details($sale, null),
            'products' => $this->sales->productsOf($sale, null),
        ]);
    }
}
