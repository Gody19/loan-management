<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\View\View;

/**
 * Minimal in-app notification inbox (Phase 11.9).
 *
 * Notifications are personal rows: a user only ever sees and acts on their own
 * rows, and there is no global inbox to federate. Reading a notification is a
 * UI state change only — the underlying insight still needs an explicit human
 * acknowledge/resolve/dismiss on the dashboard (permission ai.insights.view).
 */
class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $notifications = $request->user()
            ->notifications()
            ->latest()
            ->paginate(20);

        return view('notifications.index', [
            'notifications' => $notifications,
        ]);
    }

    public function read(Request $request, DatabaseNotification $notification): RedirectResponse
    {
        if ($notification->notifiable_type !== $request->user()->getMorphClass()
            || (int) $notification->notifiable_id !== (int) $request->user()->getKey()) {
            abort(404, 'Notification not found.');
        }

        if ($notification->read_at === null) {
            $notification->markAsRead();
        }

        return back()->with('success', 'Notification marked as read.');
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back()->with('success', 'All notifications marked as read.');
    }
}
