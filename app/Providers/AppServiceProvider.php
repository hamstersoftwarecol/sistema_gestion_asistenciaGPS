<?php

namespace App\Providers;

use App\Models\Setting;
use App\Models\User;
use App\Services\WorkCalendar;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(WorkCalendar::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Setting::flush();
        Paginator::useTailwind();

        // Los permisos de cada rol se resuelven como habilidades del Gate: @can('payroll.manage'), can:payroll.manage, etc.
        Gate::before(function (User $user, string $ability) {
            return $user->hasPermission($ability) ? true : null;
        });
    }
}
