<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Service\ScanService;
class ScanServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->app->bind(ScanService::class, function () {
            return new ScanService();
        });
    }
}
