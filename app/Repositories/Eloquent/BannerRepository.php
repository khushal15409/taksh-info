<?php

namespace App\Repositories\Eloquent;

use App\Models\Banner;
use App\Repositories\Contracts\BannerRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class BannerRepository implements BannerRepositoryInterface
{
    public function active(array $filters = []): Collection
    {
        $query = $this->liveQuery();

        if (! empty($filters['position'])) {
            $query->where('position', $filters['position']);
        }

        if (! empty($filters['type']) && in_array($filters['type'], Banner::TYPES, true)) {
            $query->where('type', $filters['type']);
        }

        if (! empty($filters['fc_id'])) {
            $fcId = (int) $filters['fc_id'];

            $query->where(function ($q) use ($fcId) {
                $q->whereHas('fulfillmentCenters', fn ($fc) => $fc->where('fulfillment_centers.id', $fcId))
                    ->orWhereDoesntHave('fulfillmentCenters'); // global banners
            });
        }

        return $this->ordered($query)->get();
    }

    public function standardForDashboard(): Collection
    {
        return $this->ordered(
            $this->liveQuery()->with('afterSection:id,section_name,is_active')->where('type', Banner::TYPE_STANDARD)
        )->get();
    }

    /** Switched on and inside its optional start/end dates. */
    private function liveQuery(): Builder
    {
        $now = Carbon::now();

        return Banner::query()
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('start_date')->orWhere('start_date', '<=', $now))
            ->where(fn ($q) => $q->whereNull('end_date')->orWhere('end_date', '>=', $now));
    }

    private function ordered(Builder $query): Builder
    {
        return $query->orderBy('sort_order', 'asc')->orderBy('created_at', 'desc');
    }
}
