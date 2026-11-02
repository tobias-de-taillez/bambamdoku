<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Group;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class GroupController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/groups/Index', [
            'groups' => Group::withCount('users')->orderBy('name')->get(['id', 'name', 'description']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Group::create($request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:groups,name'],
            'description' => ['nullable', 'string', 'max:255'],
        ]));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Gruppe angelegt.')]);

        return to_route('admin.groups.index');
    }

    public function update(Request $request, Group $group): RedirectResponse
    {
        $group->update($request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('groups', 'name')->ignore($group->id)],
            'description' => ['nullable', 'string', 'max:255'],
        ]));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Gruppe gespeichert.')]);

        return to_route('admin.groups.index');
    }

    public function destroy(Group $group): RedirectResponse
    {
        $group->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Gruppe gelöscht.')]);

        return to_route('admin.groups.index');
    }
}
