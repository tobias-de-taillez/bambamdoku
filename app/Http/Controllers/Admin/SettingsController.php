<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\ClubSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('admin/Settings', ['settings' => ClubSettings::current()]);
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
}
