<?php

namespace App\Http\Controllers;

use App\Models\ContactMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ContactMessageController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'subject' => ['nullable', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        ContactMessage::create($validated);

        return redirect()->to(route('home').'#contact')
            ->with('success', 'Your message has been sent successfully. We will get back to you soon.');
    }

    public function index(Request $request): View
    {
        $messages = ContactMessage::query()
            ->orderByRaw('is_read ASC, created_at DESC')
            ->paginate(20);

        return view('contact-messages.index', compact('messages'));
    }

    public function markRead(ContactMessage $message): RedirectResponse
    {
        if (! $message->is_read) {
            $message->update(['is_read' => true]);
        }

        return redirect()->route('contact-messages.index')
            ->with('success', 'Message marked as read.');
    }

    public function destroy(ContactMessage $message): RedirectResponse
    {
        $message->delete();

        return redirect()->route('contact-messages.index')
            ->with('success', 'Contact message deleted successfully.');
    }
}