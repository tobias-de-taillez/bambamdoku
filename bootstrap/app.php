<?php

use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\LogoutInactiveUsers;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            LogoutInactiveUsers::class,
        ]);

        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Die Kiosk-PIN landet nie als „alte Eingabe“ in der Sitzung.
        $exceptions->dontFlash(['pin', 'pin_confirmation']);

        // Abgelaufene Sitzung (z. B. Kiosk nach Standby): neu laden statt hängen zu bleiben.
        $exceptions->respond(function (Response $response, Throwable $e, Request $request) {
            if ($response->getStatusCode() === 419 && $request->header('X-Inertia')) {
                Inertia::flash('toast', ['type' => 'info', 'message' => __('Die Seite war abgelaufen. Bitte noch einmal versuchen.')]);

                return back();
            }

            // Fehlerseiten der App (mit Knopf „Report Bug / Request Feature“) statt der nackten Laravel-Seiten.
            // Serverfehler nur ohne Debug-Modus, damit lokal die Fehlerseite mit Stacktrace bleibt.
            $status = $response->getStatusCode();
            // Nur bei Seitenaufrufen (GET); Formular-Antworten (POST) bleiben wie bisher.
            if ($request->isMethodSafe() && ! $request->expectsJson() && ! $request->is('api/*')
                && (in_array($status, [403, 404, 429], true) || ($status === 500 && ! config('app.debug')))) {
                // Ohne passende Route läuft HandleInertiaRequests nicht; was die Seite braucht, kommt daher hier mit.
                return Inertia::render('Error', ['status' => $status, 'hasImprint' => filled(config('verein.imprint'))])
                    ->toResponse($request)
                    ->setStatusCode($status);
            }

            return $response;
        });
    })->create();
