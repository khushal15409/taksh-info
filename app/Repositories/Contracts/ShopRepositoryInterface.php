<?php

namespace App\Repositories\Contracts;

use App\Models\Vendor;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/** The shops customers can browse: approved vendors, with the number of standard products they sell. Read only. */
interface ShopRepositoryInterface
{
    /**
     * @param  int[]  $categoryIds  shops that sell in any of these categories (their products', or the store categories they chose); empty = all
     * @param  ?string  $location  a shop's city, as locationOptions() names it; null = anywhere
     */
    public function paginate(string $q, array $categoryIds, ?string $location, string $sort, int $perPage): LengthAwarePaginator;

    /** @return list<array{value: string, label: string, count: int}> the cities the shops are in (city from the vendor record, else from its pincode) */
    public function locationOptions(): array;

    /** @param int[] $vendorIds @return array<int, array{average: float, total: int}> approved-review rating per shop (shops without reviews are absent) */
    public function ratings(array $vendorIds): array;

    /** @param int[] $vendorIds @return array<int, list<string>> category names a shop sells in (store categories first, then its products'), at most $limit each */
    public function categoryNames(array $vendorIds, int $limit = 3): array;

    /** @return Collection<int, Vendor> */
    public function featured(int $limit): Collection;

    public function find(int $id): ?Vendor;

    /** @return int how many shops are listed (no filter) */
    public function total(): int;

    /** @return array<int, int> category id => shops that sell in it (their products' categories plus the store categories they chose) */
    public function categoryShopCounts(): array;

    /** @return array<int, int> category id => active standard products of the shop in it */
    public function productCategoryCounts(int $vendorId): array;

    /** @return array<int, string> category id => a picture of one of the shop's products in it */
    public function categoryProductImages(int $vendorId): array;

    public function productCount(int $vendorId): int;

    /** @return array{average: float, total: int} */
    public function rating(int $vendorId): array;
}
