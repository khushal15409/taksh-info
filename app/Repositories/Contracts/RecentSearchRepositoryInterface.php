<?php

namespace App\Repositories\Contracts;

interface RecentSearchRepositoryInterface
{
    /** Keywords a customer searched for, latest first. @return string[] */
    public function keywords(int $userId): array;

    /** Remember a keyword (moves it to the top when repeated) and keep only the latest ten. */
    public function record(int $userId, string $keyword): void;
}
