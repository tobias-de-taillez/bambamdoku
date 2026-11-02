<?php

namespace App\Http\Controllers\Admin;

use App\Enums\WeaponType;
use App\Http\Controllers\Controller;
use App\Models\Range;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class RangeController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/ranges/Index', [
            'ranges' => Range::ordered()->get()->map(fn (Range $range) => [
                'id' => $range->id,
                'name' => $range->name,
                'lanes' => $range->lanes,
                'weapon_types' => $range->weapon_types->map(fn (WeaponType $type) => $type->value)->values(),
                'notes' => $range->notes,
                'position' => $range->position,
                'active' => $range->active,
            ]),
            'weaponTypes' => WeaponType::options(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Range::create([...$this->validated($request), 'position' => (int) Range::max('position') + 1]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Stand angelegt.')]);

        return to_route('admin.ranges.index');
    }

    public function update(Request $request, Range $range): RedirectResponse
    {
        $range->update($this->validated($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Stand gespeichert.')]);

        return to_route('admin.ranges.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'lanes' => ['required', 'integer', 'min:1', 'max:100'],
            'weapon_types' => ['required', 'array', 'min:1'],
            'weapon_types.*' => [Rule::enum(WeaponType::class)],
            'notes' => ['nullable', 'string', 'max:255'],
            'active' => ['boolean'],
        ], [], [
            'lanes' => __('Bahnen'),
            'weapon_types' => __('Waffenarten'),
            'notes' => __('Hinweis'),
        ]);
    }
}
