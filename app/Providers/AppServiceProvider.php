<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\ServiceProvider;
use Override;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    #[Override]
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->isProduction()) {
            foreach (['vkvpa.contact_mail', 'vkvpa.contact_name'] as $key) {
                if (blank(Config::get($key))) {
                    throw new RuntimeException("Required config '{$key}' is not configured for production.");
                }
            }
        }
    }
}
