<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Http\Responses\RegisterResponse;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\LoginRateLimiter;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(RegisterResponseContract::class, RegisterResponse::class);
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureActions();
        $this->configureViews();
        $this->configureRateLimiting();
    }

    /**
     * Configure Fortify actions.
     */
    private function configureActions(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::createUsersUsing(CreateNewUser::class);

        // Deaktivierte Mitglieder können sich nicht anmelden.
        Fortify::authenticateUsing(function (Request $request) {
            $user = User::where('email', $request->string(Fortify::username()))->first();

            // Ohne Konto oder ohne Passwort trotzdem einmal hashen: Sonst verrät die Antwortzeit, ob es
            // zu der Adresse ein Konto gibt, obwohl die Meldung dieselbe ist.
            if ($user?->password === null) {
                Hash::make((string) $request->string('password'));

                return null;
            }

            if (! Hash::check($request->string('password'), $user->password)) {
                return null;
            }

            // Nur mit richtigem Passwort verraten, dass es den Antrag gibt.
            if ($user->isApplicant()) {
                throw ValidationException::withMessages([
                    Fortify::username() => __('Ihr Antrag liegt beim Vorstand. Sie können sich anmelden, sobald der Verein Sie aufgenommen hat.'),
                ]);
            }

            // Ebenso nur mit richtigem Passwort: Hinweis auf den deaktivierten Zugang. Die Drossel der
            // Route (throttle:login) zählt jeden Versuch, auch diesen. Fortifys eigenen Zähler erhöhen wir
            // wie bei einem falschen Passwort, damit beide Fälle auch dort gleich aussehen.
            if (! $user->active) {
                app(LoginRateLimiter::class)->increment($request);

                throw ValidationException::withMessages([
                    Fortify::username() => __('Ihr Zugang ist deaktiviert. Bitte wenden Sie sich an den Vorstand.'),
                ]);
            }

            return $user;
        });
    }

    /**
     * Configure Fortify views.
     */
    private function configureViews(): void
    {
        Fortify::loginView(fn (Request $request) => Inertia::render('auth/Login', [
            'canResetPassword' => Features::enabled(Features::resetPasswords()),
            'canRegister' => CreateNewUser::open(),
            'status' => $request->session()->get('status'),
        ]));

        Fortify::registerView(function () {
            abort_unless(CreateNewUser::open(), 404);

            return Inertia::render('auth/Register', [
                'passwordRules' => Password::defaults()->toPasswordRulesString(),
            ]);
        });

        Fortify::resetPasswordView(fn (Request $request) => Inertia::render('auth/ResetPassword', [
            'email' => $request->email,
            'token' => $request->route('token'),
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]));

        Fortify::requestPasswordResetLinkView(fn (Request $request) => Inertia::render('auth/ForgotPassword', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::verifyEmailView(fn (Request $request) => Inertia::render('auth/VerifyEmail', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::twoFactorChallengeView(fn () => Inertia::render('auth/TwoFactorChallenge'));

        Fortify::confirmPasswordView(fn () => Inertia::render('auth/ConfirmPassword'));
    }

    /**
     * Configure rate limiting.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        // Passwortbestätigung: 5 Versuche je Minute und Nutzer (routes/web.php, password.confirm.store)
        RateLimiter::for('password-confirm', function (Request $request) {
            return Limit::perMinute(5)->by('password-confirm|'.($request->user()?->getAuthIdentifier() ?? $request->ip()));
        });

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });
    }
}
