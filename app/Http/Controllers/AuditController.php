<?php

namespace App\Http\Controllers;

use App\Models\AuditEvent;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditController extends Controller
{
    public function index(Request $request): Response
    {
        $action = $request->query('action');
        $events = AuditEvent::with('user:id,name')
            ->where('tenant_id', $request->user()->tenant_id)
            ->when($action, fn ($q) => $q->where('action', 'like', $action.'%'))
            ->orderByDesc('id')
            ->paginate(50)->withQueryString()
            ->through(fn (AuditEvent $e) => [
                'id' => $e->id,
                'action' => $e->action,
                'user' => $e->user?->name,
                'subject' => $e->subject_type ? "{$e->subject_type} #{$e->subject_id}" : null,
                'before' => $e->before,
                'after' => $e->after,
                'reason' => $e->reason,
                'ip' => $e->ip,
                'createdAt' => $e->created_at->toIso8601String(),
                'hash' => substr($e->hash, 0, 12),
            ]);

        return Inertia::render('Audit/Index', [
            'events' => $events,
            'action' => $action,
            'verification' => $request->session()->get('verification'),
        ]);
    }

    public function verify(AuditLogger $audit): RedirectResponse
    {
        $result = $audit->verify();
        $audit->log('audit.verified', null, null, $result);

        return back()->with('verification', $result);
    }
}
