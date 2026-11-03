<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/** The website's routes are website routes: no "api." names, no duplicates, no accidental unnamed-group names. */
class RouteNamingTest extends TestCase
{
    private function names(): array
    {
        return collect(Route::getRoutes()->getRoutes())->map->getName()->filter()->values()->all();
    }

    public function test_no_route_name_starts_with_api(): void
    {
        $this->assertSame([], array_values(array_filter($this->names(), fn ($n) => str_starts_with($n, 'api.'))), 'Rename these routes: the website has no "api." names.');
    }

    public function test_route_names_are_unique(): void
    {
        $dups = array_keys(array_filter(array_count_values($this->names()), fn ($c) => $c > 1));

        $this->assertSame([], $dups, 'These route names are used more than once.');
    }

    public function test_no_route_is_named_by_its_group_prefix_alone(): void
    {
        // an unnamed route inside Route::name('x.')->group() silently becomes "x." - give it a real name
        $this->assertSame([], array_values(array_filter($this->names(), fn ($n) => str_ends_with($n, '.'))));
    }

    public function test_the_route_table_can_be_cached(): void
    {
        $this->artisan('route:cache')->assertSuccessful();
        $this->artisan('route:clear')->assertSuccessful();
    }
}
