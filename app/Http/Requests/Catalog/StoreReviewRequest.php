<?php

namespace App\Http\Requests\Catalog;

class StoreReviewRequest extends CatalogFormRequest
{
    public function rules(): array
    {
        return [
            'rating' => ['required', 'integer', 'between:1,5'],
            'review_title' => ['nullable', 'string', 'max:150'],
            'review_text' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
