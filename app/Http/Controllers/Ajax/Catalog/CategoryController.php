<?php

namespace App\Http\Controllers\Ajax\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\CategoryTreeRequest;
use App\Http\Requests\Catalog\SubCategoryRequest;
use App\Services\CategoryService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    use ApiResponseTrait;

    public function __construct(private readonly CategoryService $categories) {}

    /** GET /ecommerce/categories?delivery_type= */
    public function index(CategoryTreeRequest $request): JsonResponse
    {
        return $this->success($this->categories->tree($request->query('delivery_type')), 'api.categories_fetched');
    }

    /** GET /ecommerce/categories/parents */
    public function parents(Request $request): JsonResponse
    {
        return $this->success($this->categories->parents($request->query('delivery_type')), 'api.categories_fetched');
    }

    /** GET /ecommerce/categories/subcategories?parent_id= */
    public function subCategories(SubCategoryRequest $request): JsonResponse
    {
        $data = $this->categories->children((int) $request->validated('parent_id'), $request->validated('delivery_type'));

        return $data
            ? $this->success($data, 'api.categories_fetched')
            : $this->validationError(['parent_id' => ['The selected parent category is invalid.']]);
    }
}
