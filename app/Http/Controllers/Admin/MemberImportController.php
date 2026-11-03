<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Gerüst, wird im Bau ersetzt. */
class MemberImportController extends Controller
{
    public function create(Request $request): Response
    {
        abort(501);
    }

    public function template(): StreamedResponse
    {
        abort(501);
    }

    public function preview(Request $request): RedirectResponse
    {
        abort(501);
    }

    public function store(Request $request): RedirectResponse
    {
        abort(501);
    }

    public function cancel(Request $request): RedirectResponse
    {
        abort(501);
    }
}
