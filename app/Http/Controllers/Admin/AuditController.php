<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditEntry;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));

        $entries = AuditEntry::query()
            ->with('actor:id,name')
            ->when($search !== '', fn ($q) => $q->where('summary', 'like', "%{$search}%"))
            ->latest('created_at')
            ->latest('id')
            ->paginate(50)
            ->withQueryString()
            ->through(fn (AuditEntry $e) => [
                'id' => $e->id,
                'at' => $e->created_at->toIso8601String(),
                'actor' => $e->actor?->name,
                'action' => $e->action,
                'summary' => $e->summary,
            ]);

        return Inertia::render('admin/Audit', [
            'entries' => $entries,
            'search' => $search,
        ]);
    }
}
