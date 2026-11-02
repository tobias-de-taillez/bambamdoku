<?php

namespace App\Http\Controllers;

use App\Domain\Bookings;
use App\Domain\TimeWindow;
use App\Models\Range;
use App\Models\ShootingDay;
use App\Models\Supervision;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SupervisionController extends Controller
{
    public function store(Request $request, ShootingDay $shootingDay, Bookings $bookings): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $data = $request->validate([
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'range_id' => ['nullable', 'integer', 'exists:ranges,id'],
            'starts_at' => ['required', 'date_format:H:i'],
            'ends_at' => ['required', 'date_format:H:i', 'after:starts_at'],
        ], [], [
            'user_id' => __('Aufsicht'),
            'range_id' => __('Stand'),
            'starts_at' => __('Beginn'),
            'ends_at' => __('Ende'),
        ]);

        $supervisor = isset($data['user_id']) ? User::query()->findOrFail((int) $data['user_id']) : $actor;

        $bookings->assignSupervision(
            $actor,
            $shootingDay,
            $supervisor,
            isset($data['range_id']) ? Range::query()->findOrFail((int) $data['range_id']) : null,
            TimeWindow::fromStrings($data['starts_at'], $data['ends_at']),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => $supervisor->is($actor)
            ? __('Danke! Sie sind als Standaufsicht eingetragen.')
            : __(':name ist als Standaufsicht eingetragen.', ['name' => $supervisor->name])]);

        return back();
    }

    public function destroy(Request $request, Supervision $supervision, Bookings $bookings): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $bookings->removeSupervision($actor, $supervision);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Aufsicht ausgetragen.')]);

        return back();
    }
}
