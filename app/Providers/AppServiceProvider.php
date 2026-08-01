<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Every permission slug in the seeded catalogue becomes a usable
        // ability automatically — $user->can('orders.create') / @can(...) /
        // the `can:` middleware all resolve through User::hasPermission(),
        // which unions permissions across every active role a user holds.
        // No per-permission Gate::define() registration needed.
        Gate::before(function (User $user, string $ability) {
            return $user->hasPermission($ability) ?: null;
        });
    }
}
