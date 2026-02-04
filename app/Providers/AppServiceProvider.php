<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Http\Middleware\TrimStrings;
use Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Foundation\Http\Middleware\ValidatePostSize;

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
    public function boot(Kernel $kernel): void
    {
        $this->app['router']->aliasMiddleware('jwt.auth',\App\Http\Middleware\JWTMiddleware::class);
        $this->app['router']->aliasMiddleware('superadmin', \App\Http\Middleware\SuperAdminMiddleware::class);
        $this->app['router']->aliasMiddleware('superadminbadilum', \App\Http\Middleware\SuperAdminBadilumMiddleware::class);
        $this->app['router']->aliasMiddleware('isAdminBadilum', \App\Http\Middleware\AdminBadilumMiddleware::class);
        $this->app['router']->aliasMiddleware('isAdminSatker', \App\Http\Middleware\AdminSatkerMiddleware::class);
        $this->app['router']->aliasMiddleware('checkSign', \App\Http\Middleware\CheckSignature::class);
        $this->app['router']->aliasMiddleware('throttleSurvey', \App\Http\Middleware\ThrottleIsiSurvey::class);
        Route::middleware([
            \Illuminate\Http\Middleware\TrustProxies::class,
            \Illuminate\Foundation\Http\Middleware\ValidatePostSize::class,
            \Illuminate\Foundation\Http\Middleware\TrimStrings::class,
            \Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull::class,
        ])->group(function () {
            // semua route
        });

         Route::middlewareGroup('api', [
            'throttle:60,1',
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            // \App\Http\Middleware\JwtMiddleware::class, // optional
        ]);
    }
}
