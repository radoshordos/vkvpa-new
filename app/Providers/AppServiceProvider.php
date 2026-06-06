<?php

declare(strict_types=1);

namespace App\Providers;

use App\Events\EdiImported;
use App\Listeners\SendEdiMailsListener;
use App\Support\VkvpaSettings;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
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
        // Mimo produkci běží Eloquent ve striktním režimu: lazy loading vztahů
        // (zdroj N+1) a tiché zahazování nevyplnitelných atributů vyhodí výjimku,
        // takže se chyby odhalí už ve vývoji/testech, ne až podle výkonu v provozu.
        // preventAccessingMissingAttributes ZÁMĚRNĚ nezapínáme – legacy modely
        // (Edihead/Ediline) pracují s dynamickými sloupci typu `Received-WWL`.
        Model::preventLazyLoading(! $this->app->isProduction());
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        Event::listen(EdiImported::class, SendEdiMailsListener::class);

        $this->configureRateLimiters();

        if ($this->app->isProduction()) {
            foreach ([VkvpaSettings::contactMail(), VkvpaSettings::contactName()] as $value) {
                if (blank($value)) {
                    throw new RuntimeException('Required vkvpa contact config is not set for production.');
                }
            }

            if (! config('session.secure')) {
                throw new RuntimeException('SESSION_SECURE_COOKIE must be true in production (HTTPS required).');
            }

            if (! config('session.encrypt')) {
                throw new RuntimeException('SESSION_ENCRYPT must be true in production.');
            }
        }
    }

    private function configureRateLimiters(): void
    {
        RateLimiter::for('edi-upload', function (Request $request): Limit {
            return Limit::perMinute(10)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('diskuse', function (Request $request): Limit {
            return Limit::perMinute(5)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('login', function (Request $request): Limit {
            return Limit::perMinute(5)->by($request->ip());
        });

        RateLimiter::for('login-token', function (Request $request): Limit {
            return Limit::perMinute(10)->by($request->ip());
        });

        RateLimiter::for('hlaseni', function (Request $request): Limit {
            return Limit::perMinute(15)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('api', function (Request $request): Limit {
            return Limit::perMinute(60)->by($request->ip());
        });
    }
}
