<?php

namespace App\Providers;

use App\Http\Resources\UserResource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // The URL generator that writes opaque keys for routes marked `urlkey:` (see App\Routing\KeyedUrlGenerator). Built exactly like the
        // framework's own; its "extend" steps (signed URLs, session, key resolver) still apply to this binding.
        $this->app->singleton('url', function ($app) {
            $routes = $app['router']->getRoutes();
            $app->instance('routes', $routes);

            return new \App\Routing\KeyedUrlGenerator(
                $routes,
                $app->rebinding('request', fn ($app, $request) => $app['url']->setRequest($request)),
                $app['config']['app.asset_url']
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // @rupee($amount) -> ₹1,234   @rupee2($amount) -> ₹1,234.50 (same as the pages' money() / money2())
        \Illuminate\Support\Facades\Blade::directive('rupee', fn ($e) => "<?php echo e(\\App\\Helpers\\Rupee::format({$e})); ?>");
        \Illuminate\Support\Facades\Blade::directive('rupee2', fn ($e) => "<?php echo e(\\App\\Helpers\\Rupee::format({$e}, 2)); ?>");

        // Hand the signed-in customer to the page shell so the header and login walls render correctly on first paint.
        View::composer('layouts.app', function ($view) {
            try {
                $user = Auth::user();
                $view->with('authUser', $user ? UserResource::make($user)->resolve() : null);
                $view->with('headerState', app(\App\Services\HeaderStateService::class)->get());
                $view->with('footerCategories', app(\App\Services\FooterCategories::class)->get());
            } catch (Throwable $e) {
                report($e);
                $view->with('authUser', null);
                $view->with('headerState', ['cart_count' => 0, 'cart_total' => 0.0, 'wish_ids' => []]);
                $view->with('footerCategories', []);
            }

            // the delivery place is a nicety: if it cannot be worked out the header just says "Set location"
            try {
                $view->with('deliverTo', app(\App\Services\Quick\QuickLocation::class)->current(request()));
            } catch (Throwable $e) {
                report($e);
                $view->with('deliverTo', ['pincode' => null, 'address_id' => null, 'label' => '']);
            }
        });
    }
}
