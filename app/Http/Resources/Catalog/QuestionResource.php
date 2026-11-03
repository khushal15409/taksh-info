<?php

namespace App\Http\Resources\Catalog;

use App\Http\Resources\ApiResource;
use Illuminate\Http\Request;

class QuestionResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        return [
            'question' => $this->question,
            'answers' => $this->answers->map(fn ($a) => ['answer' => $a->answer, 'created_at' => $a->created_at?->format('Y-m-d')])->all(),
        ];
    }
}
