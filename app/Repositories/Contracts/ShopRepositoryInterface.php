<?php

namespace App\Repositories\Contracts;

use App\Models\Vendor;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/** The shops customers can browse: approved vendors, with the number of standard products they sell. Read only. */
interface ShopRepositoryInterface
{
    /** @param int[] $categoryIds shops whose store categories include any of these (empty = all) */
    public function paginate(string $q, array $categoryIds, string $sort, int $perPage): LengthAwarePaginator;

    /** @return Collection<int, Vendor> */
    public function featured(int $limit): Collection;

    public function find(int $id): ?Vendor;

    /** @return int how many shops are listed (no filter) */
    public function total(): int;

    /** @return array<int, int> category id => shops that sell in it */
    public function categoryShopCounts(): array;

    /** @return array<int, int> category id => active standard products of the shop in it */
    public function productCategoryCounts(int $vendorId): array;

    /** @return array<int, string> category id => a picture of one of the shop's products in it */
    public function categoryProductImages(int $vendorId): array;

    public function productCount(int $vendorId): int;

    /** @return array{average: float, total: int} */
    public function rating(int $vendorId): array;
}
