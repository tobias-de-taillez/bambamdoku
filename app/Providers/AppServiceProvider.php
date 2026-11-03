<?php

namespace App\Providers;

use App\Support\ClubSettings;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

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
        $this->configureDefaults();
        ClubSettings::apply();

        // „Report Bug / Request Feature“: 10 Meldungen je Stunde und IP, Angemeldete zusätzlich 10 je Stunde und Konto.
        RateLimiter::for('help-requests', function (Request $request) {
            $limits = [Limit::perHour(10)->by('help-ip:'.$request->ip())];
            if ($request->user() !== null) {
                $limits[] = Limit::perHour(10)->by('help-user:'.$request->user()->getAuthIdentifier());
            }

            return $limits;
        });

        // Auf dem Webhosting liegt der Docroot außerhalb der App (index.php setzt usePublicPath).
        // laravel-dompdf nimmt sonst base_path('public'), das es dort nicht gibt: jedes PDF scheitert.
        config(['dompdf.public_path' => public_path()]);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
