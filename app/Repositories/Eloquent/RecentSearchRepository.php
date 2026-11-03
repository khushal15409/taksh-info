<?php

namespace App\Repositories\Eloquent;

use App\Models\RecentSearch;
use App\Repositories\Contracts\RecentSearchRepositoryInterface;

class RecentSearchRepository implements RecentSearchRepositoryInterface
{
    public function keywords(int $userId): array
    {
        return RecentSearch::where('user_id', $userId)->orderBy('updated_at', 'desc')->pluck('keyword')->all();
    }

    public function record(int $userId, string $keyword): void
    {
        $existing = RecentSearch::where('user_id', $userId)->where('keyword', $keyword)->first();

        $existing ? $existing->touch() : RecentSearch::create(['user_id' => $userId, 'keyword' => $keyword]);

        $stale = RecentSearch::where('user_id', $userId)->orderBy('updated_at', 'desc')->orderBy('id', 'desc')->skip(10)->take(PHP_INT_MAX)->pluck('id');
        if ($stale->isNotEmpty()) {
            RecentSearch::whereIn('id', $stale)->delete();
        }
    }
}
