<?php

namespace App\Providers;

use App\Services\BiopagoApiService;
use App\Services\BiopagoAuthService;
use App\Services\PaymentService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(BiopagoAuthService::class);
        $this->app->singleton(BiopagoApiService::class);
        $this->app->singleton(PaymentService::class);
    }

    public function boot(): void
    {
        //
    }
}
