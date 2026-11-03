<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use Symfony\Component\HttpFoundation\Response;

/**
 * Selbstregistrierung = Mitgliedsantrag (E7, E8). Legt immer einen Antrag an
 * (role=applicant, active=false), gleich was im Request steht.
 */
class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Versuche je IP und Stunde; die Fortify-Route selbst hat keinen Throttle.
     */
    public const MAX_ATTEMPTS = 10;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, mixed>  $input
     */
    public function create(array $input): User
    {
        abort_unless(self::open(), 404);

        $key = 'register|'.request()->ip();

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            throw ValidationException::withMessages([
                'email' => __('Zu viele Versuche. Bitte in :minutes Minuten erneut versuchen.', ['minutes' => (int) ceil(RateLimiter::availableIn($key) / 60)]),
            ])->status(Response::HTTP_TOO_MANY_REQUESTS);
        }

        RateLimiter::hit($key, 3600);

        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
            // Pflichthaken, wird nicht gespeichert: Volljährigkeit prüft der Vorstand beim Aufnehmen.
            'adult' => ['accepted'],
        ], [
            'adult.accepted' => __('Mitglied werden kann hier nur, wer volljährig ist. Minderjährige melden sich bitte beim Vorstand.'),
        ], [
            'name' => __('Name'),
        ])->validate();

        $user = new User;
        $user->forceFill([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => $input['password'],
            'role' => Role::Applicant,
            'active' => false,
            'email_verified_at' => null,
        ])->save();

        return $user;
    }

    /**
     * Schalter „Mitgliedsanträge online“ in den Einstellungen (Standard: aus).
     */
    public static function open(): bool
    {
        return (int) config('verein.registration_open', 0) === 1;
    }
}
