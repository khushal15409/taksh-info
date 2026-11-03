<?php

namespace App\Helpers;

use Illuminate\Support\Collection;

class RatingSummary
{
    /**
     * Average, count and 5..1 star breakup of already approved reviews.
     *
     * @return array{average_rating: float|int, total_reviews: int, rating_breakup: object}
     */
    public static function for(Collection $reviews): array
    {
        $total = $reviews->count();

        return [
            'average_rating' => $total > 0 ? round($reviews->avg('rating'), 1) : 0,
            'total_reviews' => $total,
            // an object, so it is always sent as {"5": n, ... "1": n} (a plain 5..1 array would be re-indexed into a list)
            'rating_breakup' => (object) [
                '5' => $reviews->where('rating', 5)->count(),
                '4' => $reviews->where('rating', 4)->count(),
                '3' => $reviews->where('rating', 3)->count(),
                '2' => $reviews->where('rating', 2)->count(),
                '1' => $reviews->where('rating', 1)->count(),
            ],
        ];
    }
}
