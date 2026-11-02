<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\ClubSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class SettingsController extends Controller
{
    public function edit(): Response
    {
        $mailer = (string) config('mail.default');

        return Inertia::render('admin/Settings', [
            'settings' => ClubSettings::current(),
            'mail' => [
                'mailer' => $mailer,
                'delivers' => ! in_array($mailer, ['log', 'array'], true),
                'from' => config('mail.from.address'),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:200'],
            'slot_minutes' => ['required', 'integer', 'in:15,30,60,90,120'],
            'cancel_minutes_before' => ['required', 'integer', 'min:0', 'max:10080'],
            'booking_horizon_days' => ['required', 'integer', 'min:1', 'max:365'],
        ], [], [
            'name' => __('Vereinsname'),
            'address' => __('Anschrift'),
            'slot_minutes' => __('Buchungsraster'),
            'cancel_minutes_before' => __('Stornofrist'),
            'booking_horizon_days' => __('Buchungshorizont'),
        ]);

        ClubSettings::save($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Einstellungen gespeichert.')]);

        return to_route('admin.settings.edit');
    }

    public function testMail(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        try {
            Mail::raw(__('Diese Testmail bestätigt, dass bambamdoku Mails verschicken kann.'), function ($message) use ($user) {
                $message->to($user->email)->subject(__('Testmail von :app', ['app' => config('app.name')]));
            });
        } catch (Throwable $e) {
            report($e);

            throw ValidationException::withMessages(['mail' => __('Versand fehlgeschlagen: :error', ['error' => $e->getMessage()])]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Testmail an :email verschickt.', ['email' => $user->email])]);

        return to_route('admin.settings.edit');
    }
}
